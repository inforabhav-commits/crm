<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\User;
use App\Services\CrmNotificationService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('opportunities.view');

        $filters = $request->only(['search', 'stage', 'owner', 'status', 'customer']);
        $query = Opportunity::with(['customer', 'contact', 'stage', 'owner'])
            ->visibleTo($request->user())
            ->latest();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['stage'])) {
            $query->where('stage_id', $filters['stage']);
        }

        if (! empty($filters['owner'])) {
            $query->where('owner_id', $filters['owner']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer'])) {
            $customer = Customer::visibleTo($request->user())->findOrFail($filters['customer']);
            $query->where('customer_id', $customer->id);
        }

        return view('opportunities.index', [
            'opportunities' => $query->paginate(15)->withQueryString(),
            'stages' => $this->stages(),
            'owners' => $this->ownerOptions($request->user()),
            'customers' => Customer::visibleTo($request->user())->orderBy('name')->get(),
            'statuses' => ['open', 'won', 'lost'],
            'filters' => $filters,
        ]);
    }

    public function pipeline(Request $request)
    {
        $this->authorize('opportunities.view');

        return view('opportunities.pipeline', [
            'stages' => $this->stages(),
            'opportunitiesByStage' => Opportunity::with(['customer', 'owner', 'stage'])
                ->visibleTo($request->user())
                ->orderBy('expected_close_date')
                ->get()
                ->groupBy('stage_id'),
            'lossReasons' => $this->lossReasons(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('opportunities.create');
        $stage = $this->defaultStage();

        return view('opportunities.create', $this->formData(new Opportunity([
            'stage_id' => $stage?->id,
            'owner_id' => $request->user()->id,
            'currency' => 'USD',
            'probability' => $this->defaultProbability($stage),
            'status' => 'open',
        ]), $request->user()));
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('opportunities.create');

        $validated = $this->validatedOpportunity($request);
        $stage = CrmMasterValue::findOrFail($validated['stage_id']);
        if ($this->stageKind($stage) === 'lost' && empty($validated['loss_reason_id'])) {
            return back()->withErrors(['loss_reason_id' => 'Loss reason is required when marking an opportunity lost.'])->withInput();
        }
        $this->abortIfCannotUseCustomer($request, (int) $validated['customer_id']);
        $this->abortIfCannotUseContact($request, $validated['contact_id'] ?? null, (int) $validated['customer_id']);
        $this->abortIfCannotAssignTo($request->user(), (int) $validated['owner_id']);

        $opportunity = DB::transaction(function () use ($request, $validated, $stage) {
            $opportunity = Opportunity::create($this->payload($validated, $request->user()->id, $stage));
            $this->recordStageHistory($opportunity, null, (int) $validated['stage_id'], $request->user()->id, 'Created');

            return $opportunity;
        });
        $audit->created($opportunity, 'opportunity.created', 'Opportunity created.', $request->user(), $request);
        app(\App\Services\WorkflowRuleService::class)->dispatch($opportunity, 'record_created', ['event_key' => 'opportunity-created:'.$opportunity->id], $request->user());

        return redirect()->route('opportunities.show', $opportunity)->with('status', 'Opportunity created.');
    }

    public function show(Request $request, Opportunity $opportunity)
    {
        $this->authorize('opportunities.view');
        $this->abortIfCannotAccessOpportunity($request, $opportunity);

        $opportunity->load(['customer', 'contact', 'sourceLead', 'stage', 'lossReason', 'owner', 'activities.type', 'activities.assignedUser', 'stageHistories.fromStage', 'stageHistories.toStage', 'stageHistories.changedBy']);
        $pendingActivities = $opportunity->activities->where('status', 'pending');

        return view('opportunities.show', [
            'opportunity' => $opportunity,
            'stages' => $this->stages(),
            'lossReasons' => $this->lossReasons(),
            'activityTypes' => $this->activityTypes(),
            'recentActivities' => $opportunity->activities->take(10),
            'upcomingActivities' => $pendingActivities->filter(fn (Activity $activity) => ! $activity->is_overdue)->sortBy('due_at')->take(5),
            'overdueActivities' => $pendingActivities->filter(fn (Activity $activity) => $activity->is_overdue)->sortBy('due_at')->take(5),
        ]);
    }

    public function edit(Request $request, Opportunity $opportunity)
    {
        $this->authorize('opportunities.edit');
        $this->abortIfCannotAccessOpportunity($request, $opportunity);

        return view('opportunities.edit', $this->formData($opportunity, $request->user()));
    }

    public function update(Request $request, Opportunity $opportunity, AuditService $audit)
    {
        $this->authorize('opportunities.edit');
        $this->abortIfCannotAccessOpportunity($request, $opportunity);

        $validated = $this->validatedOpportunity($request);
        $stage = CrmMasterValue::findOrFail($validated['stage_id']);
        if ($this->stageKind($stage) === 'lost' && empty($validated['loss_reason_id'])) {
            return back()->withErrors(['loss_reason_id' => 'Loss reason is required when marking an opportunity lost.'])->withInput();
        }
        $this->abortIfCannotUseCustomer($request, (int) $validated['customer_id']);
        $this->abortIfCannotUseContact($request, $validated['contact_id'] ?? null, (int) $validated['customer_id']);
        $this->abortIfCannotAssignTo($request->user(), (int) $validated['owner_id']);

        DB::transaction(function () use ($request, $opportunity, $validated, $stage, $audit) {
            $before = $opportunity->getAttributes();
            $fromStageId = $opportunity->stage_id;
            $opportunity->fill($this->payload($validated, $request->user()->id, $stage, false))->save();
            $audit->updated($opportunity, $before, 'opportunity.updated', 'Opportunity updated.', $request->user(), $request);

            if ((int) $fromStageId !== (int) $opportunity->stage_id) {
                $history = $this->recordStageHistory($opportunity, $fromStageId, $opportunity->stage_id, $request->user()->id, $validated['stage_notes'] ?? null);
                app(AuditService::class)->log('opportunity.stage_changed', $opportunity, 'Opportunity stage changed.', ['stage_id' => $fromStageId], ['stage_id' => $opportunity->stage_id, 'status' => $opportunity->status], $request->user(), $request);
                app(CrmNotificationService::class)->notifyOpportunityStageChange($history);
                app(\App\Services\WorkflowRuleService::class)->dispatch($opportunity->refresh(), 'opportunity_stage_changed', ['previous_stage' => $fromStageId, 'event_key' => 'opportunity-stage:'.$history->id], $request->user());
            }
        });

        return redirect()->route('opportunities.show', $opportunity)->with('status', 'Opportunity updated.');
    }

    public function changeStage(Request $request, Opportunity $opportunity)
    {
        $this->authorize('opportunities.change_stage');
        $this->abortIfCannotAccessOpportunity($request, $opportunity);

        $validated = $request->validate([
            'stage_id' => ['required', Rule::exists('crm_master_values', 'id')->where('type', 'opportunity_stage')->where('is_active', true)],
            'loss_reason_id' => ['nullable', Rule::exists('crm_master_values', 'id')->where('type', 'loss_reason')->where('is_active', true)],
            'stage_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $stage = CrmMasterValue::findOrFail($validated['stage_id']);
        if ($this->stageKind($stage) === 'lost' && empty($validated['loss_reason_id'])) {
            return back()->withErrors(['loss_reason_id' => 'Loss reason is required when marking an opportunity lost.']);
        }

        DB::transaction(function () use ($request, $opportunity, $validated, $stage) {
            $fromStageId = $opportunity->stage_id;
            $opportunity->fill($this->stageState($stage, $validated['loss_reason_id'] ?? null) + [
                'stage_id' => $stage->id,
                'probability' => $this->defaultProbability($stage),
                'updated_by_id' => $request->user()->id,
            ])->save();

            if ((int) $fromStageId !== (int) $stage->id) {
                $history = $this->recordStageHistory($opportunity, $fromStageId, $stage->id, $request->user()->id, $validated['stage_notes'] ?? null);
                app(AuditService::class)->log('opportunity.stage_changed', $opportunity, 'Opportunity stage changed.', ['stage_id' => $fromStageId], ['stage_id' => $stage->id, 'status' => $opportunity->status], $request->user(), $request);
                app(CrmNotificationService::class)->notifyOpportunityStageChange($history);
                app(\App\Services\WorkflowRuleService::class)->dispatch($opportunity->refresh(), 'opportunity_stage_changed', ['previous_stage' => $fromStageId, 'event_key' => 'opportunity-stage:'.$history->id], $request->user());
            }
        });

        return redirect()->route('opportunities.show', $opportunity)->with('status', 'Opportunity stage updated.');
    }

    private function formData(Opportunity $opportunity, User $user): array
    {
        return [
            'opportunity' => $opportunity,
            'customers' => Customer::visibleTo($user)->orderBy('name')->get(),
            'contacts' => Contact::with('customer')->visibleTo($user)->orderBy('first_name')->orderBy('last_name')->get(),
            'owners' => $this->ownerOptions($user),
            'stages' => $this->stages(),
            'lossReasons' => $this->lossReasons(),
        ];
    }

    private function validatedOpportunity(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'stage_id' => ['required', Rule::exists('crm_master_values', 'id')->where('type', 'opportunity_stage')->where('is_active', true)],
            'owner_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'next_step' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'loss_reason_id' => ['nullable', Rule::exists('crm_master_values', 'id')->where('type', 'loss_reason')->where('is_active', true)],
            'stage_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function payload(array $validated, int $userId, CrmMasterValue $stage, bool $creating = true): array
    {
        $payload = [
            'name' => $validated['name'],
            'customer_id' => $validated['customer_id'],
            'contact_id' => $validated['contact_id'] ?? null,
            'stage_id' => $stage->id,
            'owner_id' => $validated['owner_id'],
            'amount' => $validated['amount'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'probability' => $validated['probability'] ?? $this->defaultProbability($stage),
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'next_step' => $validated['next_step'] ?? null,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'updated_by_id' => $userId,
        ] + $this->stageState($stage, $validated['loss_reason_id'] ?? null);

        if ($creating) {
            $payload['created_by_id'] = $userId;
        }

        return $payload;
    }

    private function stageState(CrmMasterValue $stage, $lossReasonId): array
    {
        if ($stage->slug === 'won') {
            return ['status' => 'won', 'loss_reason_id' => null, 'closed_at' => now(), 'won_at' => now(), 'lost_at' => null];
        }

        if ($stage->slug === 'lost') {
            return ['status' => 'lost', 'loss_reason_id' => $lossReasonId, 'closed_at' => now(), 'won_at' => null, 'lost_at' => now()];
        }

        return ['status' => 'open', 'loss_reason_id' => null, 'closed_at' => null, 'won_at' => null, 'lost_at' => null];
    }

    private function recordStageHistory(Opportunity $opportunity, ?int $fromStageId, int $toStageId, int $userId, ?string $notes): OpportunityStageHistory
    {
        return OpportunityStageHistory::create([
            'opportunity_id' => $opportunity->id,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStageId,
            'changed_by_id' => $userId,
            'notes' => $notes,
            'changed_at' => now(),
        ]);
    }

    private function abortIfCannotAccessOpportunity(Request $request, Opportunity $opportunity): void
    {
        abort_unless(Opportunity::whereKey($opportunity->id)->visibleTo($request->user())->exists(), 403);
    }

    private function abortIfCannotUseCustomer(Request $request, int $customerId): void
    {
        abort_unless(Customer::whereKey($customerId)->visibleTo($request->user())->exists(), 403);
    }

    private function abortIfCannotUseContact(Request $request, $contactId, int $customerId): void
    {
        if (! $contactId) {
            return;
        }

        abort_unless(Contact::whereKey($contactId)->where('customer_id', $customerId)->visibleTo($request->user())->exists(), 403);
    }

    private function abortIfCannotAssignTo(User $user, int $ownerId): void
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return;
        }

        $allowed = $user->reportingTreeUserIds();
        $allowed[] = $user->id;

        abort_unless(in_array($ownerId, $allowed, true), 403);
    }

    private function ownerOptions(User $user)
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return User::where('is_active', true)->orderBy('name')->get();
        }

        $ids = $user->reportingTreeUserIds();
        $ids[] = $user->id;

        return User::whereIn('id', array_unique($ids))->where('is_active', true)->orderBy('name')->get();
    }

    private function stages()
    {
        return CrmMasterValue::where('type', 'opportunity_stage')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function lossReasons()
    {
        return CrmMasterValue::where('type', 'loss_reason')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function activityTypes()
    {
        return CrmMasterValue::where('type', 'activity_type')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    private function defaultStage(): ?CrmMasterValue
    {
        return $this->stages()->first();
    }

    private function defaultProbability(?CrmMasterValue $stage): int
    {
        if (! $stage) {
            return 10;
        }

        return match ($stage->slug) {
            'won' => 100,
            'lost' => 0,
            'proposal' => 50,
            'negotiation' => 75,
            default => 10,
        };
    }

    private function stageKind(CrmMasterValue $stage): string
    {
        return in_array($stage->slug, ['won', 'lost'], true) ? $stage->slug : 'open';
    }
}
