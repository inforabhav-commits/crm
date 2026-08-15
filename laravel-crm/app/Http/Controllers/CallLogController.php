<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Integrations\JustCall\JustCallClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CallLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('calls.view');

        $filters = $request->only(['direction', 'status', 'agent', 'date', 'match']);
        $query = CallLog::with(['user', 'lead', 'customer', 'contact', 'opportunity'])
            ->visibleTo($request->user())
            ->latest('last_event_at');

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['agent'])) {
            $query->where('user_id', $filters['agent']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('last_event_at', $filters['date']);
        }

        if (($filters['match'] ?? '') === 'matched') {
            $query->where(function ($matchQuery) {
                $matchQuery->whereNotNull('lead_id')
                    ->orWhereNotNull('customer_id')
                    ->orWhereNotNull('contact_id')
                    ->orWhereNotNull('opportunity_id');
            });
        } elseif (($filters['match'] ?? '') === 'unmatched') {
            $query->whereNull('lead_id')
                ->whereNull('customer_id')
                ->whereNull('contact_id')
                ->whereNull('opportunity_id');
        }

        return view('call_logs.index', [
            'callLogs' => $query->paginate(15)->withQueryString(),
            'agents' => $this->agentOptions($request->user()),
            'directions' => ['inbound', 'outbound', 'unknown'],
            'statuses' => CallLog::visibleTo($request->user())->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, CallLog $callLog)
    {
        $this->authorize('calls.view');
        $this->abortIfCannotAccessCall($request->user(), $callLog);

        return view('call_logs.show', [
            'callLog' => $callLog->load(['user', 'lead', 'customer', 'contact', 'opportunity', 'followUpActivity']),
            'dispositions' => CallLog::DISPOSITIONS,
        ]);
    }

    public function update(Request $request, CallLog $callLog, AuditService $audit)
    {
        $this->authorize('calls.update');
        $this->abortIfCannotAccessCall($request->user(), $callLog);

        $validated = $request->validate([
            'crm_disposition' => ['nullable', Rule::in(CallLog::DISPOSITIONS)],
            'crm_notes' => ['nullable', 'string', 'max:5000'],
            'follow_up_required' => ['nullable', 'boolean'],
            'next_follow_up_at' => ['nullable', 'date', 'after_or_equal:now'],
        ]);

        DB::transaction(function () use ($request, $callLog, $validated, $audit) {
            $before = $callLog->getAttributes();
            $callLog->fill([
                'crm_disposition' => $validated['crm_disposition'] ?? null,
                'disposition' => ($validated['crm_disposition'] ?? null) ?: $callLog->provider_disposition,
                'crm_notes' => $validated['crm_notes'] ?? null,
                'notes' => ($validated['crm_notes'] ?? null) ?: $callLog->provider_notes,
                'follow_up_required' => $request->boolean('follow_up_required'),
            ])->save();

            if ($request->boolean('follow_up_required') && ! empty($validated['next_follow_up_at'])) {
                $activity = $this->createOrUpdateFollowUp($request, $callLog, $validated['next_follow_up_at']);
                $callLog->forceFill(['follow_up_activity_id' => $activity->id])->save();
            }

            $audit->updated($callLog->refresh(), $before, 'call_log.updated', 'Call disposition/notes updated.', $request->user(), $request);
        });

        return redirect()->route('calls.show', $callLog)->with('status', 'Call log updated.');
    }

    public function recording(Request $request, CallLog $callLog, JustCallClient $client, AuditService $audit)
    {
        $this->authorize('calls.recordings.view');
        $this->abortIfCannotAccessCall($request->user(), $callLog);

        if (! $callLog->hasAvailableRecording()) {
            $audit->log('call_recording.access_failed', $callLog, 'Call recording access failed.', null, [
                'success' => false,
                'reason' => 'unavailable',
                'call_log_id' => $callLog->id,
            ], $request->user(), $request);

            return back()->with('error', 'Recording is not available yet.');
        }

        $url = $callLog->recording_url ? $client->recordingAccessUrl($callLog->recording_url) : null;
        if (! $url) {
            $audit->log('call_recording.access_failed', $callLog, 'Call recording access failed.', null, [
                'success' => false,
                'reason' => 'invalid_recording_reference',
                'call_log_id' => $callLog->id,
            ], $request->user(), $request);

            return back()->with('error', 'Recording could not be opened.');
        }

        $audit->log('call_recording.accessed', $callLog, 'Call recording accessed.', null, [
            'success' => true,
            'call_log_id' => $callLog->id,
            'recording_status' => $callLog->recording_status,
        ], $request->user(), $request);

        return redirect()->away($url);
    }

    private function agentOptions(User $user)
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return User::where('is_active', true)->orderBy('name')->get();
        }

        $ids = $user->reportingTreeUserIds();
        $ids[] = $user->id;

        return User::whereIn('id', array_unique($ids))->where('is_active', true)->orderBy('name')->get();
    }

    private function abortIfCannotAccessCall(User $user, CallLog $callLog): void
    {
        abort_unless(CallLog::whereKey($callLog->id)->visibleTo($user)->exists(), 403);
    }

    private function createOrUpdateFollowUp(Request $request, CallLog $callLog, string $dueAt): Activity
    {
        [$relatedType, $relatedId] = $this->relatedPayload($callLog);
        abort_unless($relatedType && $relatedId, 422, 'A matched CRM record is required before scheduling a follow-up.');

        $payload = [
            'activity_type_id' => $this->followUpTypeId(),
            'subject' => 'Call follow-up',
            'description' => $callLog->crm_notes ?: $callLog->provider_notes,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'assigned_user_id' => $callLog->user_id ?: $request->user()->id,
            'updated_by_id' => $request->user()->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => $dueAt,
        ];

        if ($callLog->follow_up_activity_id && $callLog->followUpActivity) {
            $callLog->followUpActivity->fill($payload)->save();

            return $callLog->followUpActivity->refresh();
        }

        return Activity::create($payload + ['created_by_id' => $request->user()->id]);
    }

    private function relatedPayload(CallLog $callLog): array
    {
        if ($callLog->contact_id) {
            return [Contact::class, $callLog->contact_id];
        }

        if ($callLog->customer_id) {
            return [Customer::class, $callLog->customer_id];
        }

        if ($callLog->lead_id) {
            return [Lead::class, $callLog->lead_id];
        }

        if ($callLog->opportunity_id) {
            return [Opportunity::class, $callLog->opportunity_id];
        }

        return [null, null];
    }

    private function followUpTypeId(): int
    {
        $type = CrmMasterValue::firstOrCreate([
            'type' => 'activity_type',
            'slug' => 'follow-up',
        ], [
            'name' => 'Follow-up',
            'is_active' => true,
            'sort_order' => 4,
        ]);

        return $type->id;
    }
}
