<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\JustCallUserMapping;
use App\Models\User;
use App\Models\WebhookInboxEntry;
use App\Services\AuditService;
use App\Services\CallLogMatcher;
use App\Services\Integrations\JustCall\JustCallWebhookInboxProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JustCallMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('justcall.monitor');

        $query = WebhookInboxEntry::query()->where('provider', 'justcall');
        $callQuery = CallLog::query()->where('provider', 'justcall');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('processing_status')) {
            $query->where('processing_status', $request->processing_status);
        }

        if ($request->filled('from')) {
            $query->where('received_at', '>=', $request->from.' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('received_at', '<=', $request->to.' 23:59:59');
        }

        $summary = [
            'webhooks_received' => WebhookInboxEntry::where('provider', 'justcall')->count(),
            'pending_webhooks' => WebhookInboxEntry::where('provider', 'justcall')->where('processing_status', 'pending')->count(),
            'failed_webhooks' => WebhookInboxEntry::where('provider', 'justcall')->where('processing_status', 'failed')->count(),
            'unsupported_events' => WebhookInboxEntry::where('provider', 'justcall')->where('processing_status', 'unsupported')->count(),
            'normalized_events' => WebhookInboxEntry::where('provider', 'justcall')->whereNotNull('normalized_event_type')->count(),
            'calls_created_or_updated' => CallLog::where('provider', 'justcall')->count(),
            'unmatched_calls' => CallLog::where('provider', 'justcall')->whereNull('lead_id')->whereNull('customer_id')->whereNull('contact_id')->whereNull('opportunity_id')->count(),
            'ambiguous_calls' => CallLog::where('provider', 'justcall')->get()->filter(function (CallLog $callLog) {
                return app('App\\Services\\CallLogMatcher')->resolve($callLog->customer_number_normalized)['state'] ?? null === 'ambiguous';
            })->count(),
            'unmapped_agents' => CallLog::where('provider', 'justcall')->whereNotNull('agent_external_id')->whereNull('user_id')->count(),
            'missed_call_skipped' => CallLog::where('provider', 'justcall')->whereIn('missed_call_automation_status', ['skipped_unmapped', 'skipped_ambiguous', 'skipped_unknown', 'skipped_restricted'])->count(),
            'missed_call_failed' => CallLog::where('provider', 'justcall')->where('missed_call_automation_status', 'failed')->count(),
            'last_successful_webhook' => WebhookInboxEntry::where('provider', 'justcall')->whereNotIn('processing_status', ['pending', 'failed', 'unsupported'])->latest('received_at')->value('received_at'),
            'last_processing_failure' => WebhookInboxEntry::where('provider', 'justcall')->whereIn('processing_status', ['failed', 'unsupported'])->latest('processed_at')->value('processed_at'),
        ];

        $entries = $query->latest('received_at')->paginate(15)->withQueryString();
        $calls = $callQuery->with(['user', 'lead', 'customer', 'contact', 'opportunity'])
            ->latest('last_event_at')
            ->paginate(15)
            ->withQueryString();

        $exceptionCalls = CallLog::query()
            ->where('provider', 'justcall')
            ->where(function ($query) {
                $query->whereNull('lead_id')
                    ->whereNull('customer_id')
                    ->whereNull('contact_id')
                    ->whereNull('opportunity_id')
                    ->orWhereNotNull('agent_external_id');
            })
            ->latest('last_event_at')
            ->limit(10)
            ->get()
            ->map(function (CallLog $callLog) {
                $matchState = 'unknown';
                $matchType = null;

                if ($callLog->customer_number_normalized) {
                    $match = app('App\\Services\\CallLogMatcher')->resolve($callLog->customer_number_normalized);
                    $matchState = $match['state'] ?? 'unknown';
                    $matchType = $match['type'] ?? null;
                }

                if ($callLog->agent_external_id && empty($callLog->user_id)) {
                    $issue = 'Unmapped JustCall agent';
                } elseif ($matchState === 'ambiguous') {
                    $issue = 'Ambiguous match';
                } elseif ($callLog->lead_id === null && $callLog->customer_id === null && $callLog->contact_id === null && $callLog->opportunity_id === null) {
                    $issue = 'Unmatched call';
                } else {
                    $issue = 'Needs review';
                }

                return [
                    'id' => $callLog->external_call_id ?: $callLog->id,
                    'issue' => $issue,
                    'phone' => $callLog->customer_number ?: $callLog->from_number ?: '-',
                    'status' => $callLog->status ?: 'unknown',
                    'match_type' => $matchType,
                ];
            });

        $mappingIssues = JustCallUserMapping::with('user')
            ->where('is_active', true)
            ->get()
            ->filter(function ($mapping) {
                return ! $mapping->user || ! $mapping->user->is_active || empty($mapping->justcall_user_id);
            })
            ->values();

        return view('admin.justcall-settings.monitoring', [
            'summary' => $summary,
            'entries' => $entries,
            'calls' => $calls,
            'exceptionCalls' => $exceptionCalls,
            'mappingIssues' => $mappingIssues,
            'filters' => $request->only(['from', 'to', 'event_type', 'processing_status']),
            'eventTypes' => WebhookInboxEntry::where('provider', 'justcall')->distinct()->pluck('event_type')->filter()->values(),
            'processingStatuses' => WebhookInboxEntry::where('provider', 'justcall')->distinct()->pluck('processing_status')->filter()->values(),
        ]);
    }

    public function reconcile(Request $request)
    {
        $this->authorize('justcall.monitor');

        $query = CallLog::query()->where('provider', 'justcall')
            ->where(function ($q) {
                $q->whereNull('lead_id')
                    ->whereNull('customer_id')
                    ->whereNull('contact_id')
                    ->whereNull('opportunity_id')
                    ->orWhereNotNull('agent_external_id');
            });

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $calls = $query->with(['user'])->latest('last_event_at')->paginate(20);
        $agents = User::where('is_active', true)->orderBy('name')->get();
        $relatedOptions = [
            Lead::class => 'Lead',
            Customer::class => 'Customer',
            Contact::class => 'Contact',
        ];

        return view('admin.justcall-settings.reconcile', [
            'calls' => $calls,
            'agents' => $agents,
            'relatedOptions' => $relatedOptions,
            'filters' => $request->only(['direction', 'status']),
        ]);
    }

    public function storeReconciliation(Request $request, AuditService $audit, CallLogMatcher $matcher)
    {
        $this->authorize('justcall.manage');

        $validated = $request->validate([
            'call_log_id' => ['required', 'integer'],
            'related_type' => ['nullable', 'required_with:related_id', Rule::in([Lead::class, Customer::class, Contact::class])],
            'related_id' => ['nullable', 'required_with:related_type', 'integer'],
            'assign_agent_id' => ['nullable', 'exists:users,id'],
            'review_status' => ['nullable', Rule::in(['reviewed', 'resolved'])],
            'reconciliation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $callLog = CallLog::where('provider', 'justcall')->findOrFail($validated['call_log_id']);

        if (! empty($validated['related_type']) && ! empty($validated['related_id'])) {
            $model = $validated['related_type'];
            abort_unless($model::query()->whereKey($validated['related_id'])->visibleTo($request->user())->exists(), 422, 'Related CRM record was not found or is not visible.');
            $callLog->forceFill([
                'lead_id' => $model === Lead::class ? $validated['related_id'] : $callLog->lead_id,
                'customer_id' => $model === Customer::class ? $validated['related_id'] : $callLog->customer_id,
                'contact_id' => $model === Contact::class ? $validated['related_id'] : $callLog->contact_id,
                'opportunity_id' => $callLog->opportunity_id,
            ])->save();
        }

        if (! empty($validated['assign_agent_id'])) {
            $user = User::findOrFail($validated['assign_agent_id']);
            abort_unless($user->is_active, 422, 'Only active CRM users may be assigned.');
            $callLog->forceFill(['user_id' => $user->id])->save();
        }

        $callLog->forceFill([
            'review_status' => $validated['review_status'] ?? $callLog->review_status ?? 'reviewed',
            'reviewed_at' => now(),
            'reconciled_by_id' => $request->user()->id,
            'reconciliation_notes' => $validated['reconciliation_notes'] ?? $callLog->reconciliation_notes,
        ])->save();

        $audit->log('justcall.call_reconciled', $callLog, 'JustCall call exception reconciled.', null, [
            'related_type' => $validated['related_type'] ?? null,
            'related_id' => $validated['related_id'] ?? null,
            'assign_agent_id' => $validated['assign_agent_id'] ?? null,
            'review_status' => $validated['review_status'] ?? null,
        ], $request->user(), $request);

        return redirect()->route('admin.justcall-monitoring.reconcile')->with('status', 'Call exception reconciled.');
    }

    public function reprocess(Request $request, JustCallWebhookInboxProcessor $processor, AuditService $audit)
    {
        $this->authorize('justcall.manage');

        $validated = $request->validate([
            'entry_id' => ['required', 'exists:webhook_inbox_entries,id'],
        ]);

        $entry = WebhookInboxEntry::findOrFail($validated['entry_id']);
        if (in_array($entry->processing_status, ['unsupported'], true)) {
            return redirect()->route('admin.justcall-monitoring.index')->with('status', 'Unsupported webhook entries are not reprocessed automatically.');
        }

        $before = $entry->getAttributes();

        $result = $processor->process($entry);
        $audit->log('justcall.webhook_reprocessed', $entry, 'JustCall webhook reprocessed.', [
            'processing_status' => $before['processing_status'] ?? null,
            'attempt_count' => $before['attempt_count'] ?? 0,
        ], [
            'processing_status' => $result->processing_status,
            'attempt_count' => $result->attempt_count,
            'failure_summary' => $result->failure_summary,
        ], $request->user(), $request);

        return redirect()->route('admin.justcall-monitoring.index')->with('status', 'JustCall webhook reprocessing completed.');
    }
}
