<?php

namespace App\Http\Controllers;

use App\Models\CrmMasterValue;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Integrations\JustCall\JustCallClickToCallService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('leads.view');

        $filters = $request->only(['search', 'status', 'source', 'owner', 'priority']);
        $query = Lead::with(['status', 'source', 'owner'])
            ->visibleTo($request->user())
            ->latest();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('lead_status_id', $filters['status']);
        }

        if (! empty($filters['source'])) {
            $query->where('lead_source_id', $filters['source']);
        }

        if (! empty($filters['owner'])) {
            $query->where('owner_id', $filters['owner']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return view('leads.index', [
            'leads' => $query->paginate(15)->withQueryString(),
            'statuses' => $this->masterValues('lead_status'),
            'sources' => $this->masterValues('lead_source'),
            'owners' => $this->ownerOptions($request->user()),
            'priorities' => Lead::PRIORITIES,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('leads.create');

        return view('leads.create', $this->formData(new Lead(['priority' => 'normal']), $request->user()));
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('leads.create');

        $validated = $this->validatedLead($request);
        $ownerId = $this->resolveOwnerId($request, $validated);

        $lead = Lead::create(array_merge($validated, [
            'owner_id' => $ownerId,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]));
        $audit->created($lead, 'lead.created', 'Lead created.', $request->user(), $request);
        app(\App\Services\WorkflowRuleService::class)->dispatch($lead, 'record_created', ['event_key' => 'lead-created:'.$lead->id], $request->user());

        return redirect()->route('leads.show', $lead)->with('status', 'Lead created.');
    }

    public function show(Request $request, Lead $lead, JustCallClickToCallService $clickToCall)
    {
        $this->authorize('leads.view');
        $this->abortIfCannotAccessLead($request->user(), $lead);

        return view('leads.show', [
            'lead' => $lead->load([
                'status',
                'source',
                'owner',
                'createdBy',
                'updatedBy',
                'qualifiedBy',
                'convertedBy',
                'convertedCustomer',
                'convertedContact',
                'convertedOpportunity.stage',
                'assignmentHistories.assignedBy',
                'assignmentHistories.assignedFrom',
                'assignmentHistories.assignedTo',
                'assignmentHistories.team',
                'activities.type',
                'activities.assignedUser',
            ]),
            'assignableOwners' => $this->ownerOptions($request->user()),
            'assignableTeams' => Team::where('is_active', true)->orderBy('name')->get(),
            'activityTypes' => $this->masterValues('activity_type'),
            'callLogs' => CallLog::with('user')
                ->visibleTo($request->user())
                ->where('lead_id', $lead->id)
                ->latest('last_event_at')
                ->take(10)
                ->get(),
            'canShowCallAction' => $clickToCall->canShowFor($lead),
        ]);
    }

    public function edit(Request $request, Lead $lead)
    {
        $this->authorize('leads.edit');
        $this->abortIfCannotAccessLead($request->user(), $lead);

        return view('leads.edit', $this->formData($lead, $request->user()));
    }

    public function update(Request $request, Lead $lead, AuditService $audit)
    {
        $this->authorize('leads.edit');
        $this->abortIfCannotAccessLead($request->user(), $lead);

        $validated = $this->validatedLead($request);
        $ownerId = $this->resolveOwnerId($request, $validated);
        $before = $lead->getAttributes();
        $previousStatus = $lead->status?->slug;

        $lead->fill(array_merge($validated, [
            'owner_id' => $ownerId,
            'updated_by_id' => $request->user()->id,
        ]))->save();
        $audit->updated($lead, $before, 'lead.updated', 'Lead updated.', $request->user(), $request);
        $workflow = app(\App\Services\WorkflowRuleService::class);
        $workflow->dispatch($lead, 'record_updated', ['event_key' => 'lead-updated:'.$lead->id.':'.$lead->updated_at?->format('U.u')], $request->user());
        if ($previousStatus !== $lead->status?->slug) {
            $workflow->dispatch($lead, 'lead_status_changed', ['previous_status' => $previousStatus, 'event_key' => 'lead-status:'.$lead->id.':'.$lead->updated_at?->format('U.u')], $request->user());
        }

        return redirect()->route('leads.show', $lead)->with('status', 'Lead updated.');
    }

    private function formData(Lead $lead, User $user): array
    {
        return [
            'lead' => $lead,
            'statuses' => $this->masterValues('lead_status'),
            'sources' => $this->masterValues('lead_source'),
            'owners' => $this->ownerOptions($user),
            'priorities' => Lead::PRIORITIES,
        ];
    }

    private function validatedLead(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'lead_status_id' => ['required', Rule::exists('crm_master_values', 'id')->where('type', 'lead_status')->where('is_active', true)],
            'lead_source_id' => ['nullable', Rule::exists('crm_master_values', 'id')->where('type', 'lead_source')->where('is_active', true)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::in(Lead::PRIORITIES)],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function resolveOwnerId(Request $request, array $validated): ?int
    {
        if ($request->user()->can('leads.assign')) {
            return isset($validated['owner_id']) ? (int) $validated['owner_id'] : null;
        }

        if (! empty($validated['owner_id']) && (int) $validated['owner_id'] !== $request->user()->id) {
            abort(403);
        }

        return $request->user()->id;
    }

    private function abortIfCannotAccessLead(User $user, Lead $lead): void
    {
        abort_unless(Lead::whereKey($lead->id)->visibleTo($user)->exists(), 403);
    }

    private function masterValues(string $type)
    {
        return CrmMasterValue::where('type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function ownerOptions(User $user)
    {
        if ($user->can('leads.assign')) {
            return User::where('is_active', true)->orderBy('name')->get();
        }

        return collect([$user]);
    }
}
