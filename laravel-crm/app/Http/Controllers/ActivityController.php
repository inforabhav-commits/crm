<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('activities.view');

        $filters = $request->only(['status', 'activity_type', 'owner', 'due', 'lead', 'customer', 'contact', 'opportunity']);
        $query = Activity::with(['type', 'assignedUser', 'related'])
            ->visibleTo($request->user())
            ->orderByRaw("status = 'pending' desc")
            ->orderBy('due_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['activity_type'])) {
            $query->where('activity_type_id', $filters['activity_type']);
        }

        if (! empty($filters['owner'])) {
            $query->where('assigned_user_id', $filters['owner']);
        }

        if (! empty($filters['lead'])) {
            $lead = Lead::visibleTo($request->user())->findOrFail($filters['lead']);
            $query->whereMorphedTo('related', $lead);
        }

        if (! empty($filters['customer'])) {
            $customer = Customer::visibleTo($request->user())->findOrFail($filters['customer']);
            $query->whereMorphedTo('related', $customer);
        }

        if (! empty($filters['contact'])) {
            $contact = Contact::visibleTo($request->user())->findOrFail($filters['contact']);
            $query->whereMorphedTo('related', $contact);
        }

        if (! empty($filters['opportunity'])) {
            $opportunity = Opportunity::visibleTo($request->user())->findOrFail($filters['opportunity']);
            $query->whereMorphedTo('related', $opportunity);
        }

        if (($filters['due'] ?? '') === 'overdue') {
            $query->where('status', 'pending')->where('due_at', '<', now());
        } elseif (($filters['due'] ?? '') === 'upcoming') {
            $query->where('status', 'pending')->where('due_at', '>=', now());
        } elseif (($filters['due'] ?? '') === 'today') {
            $query->whereDate('due_at', today());
        }

        return view('activities.index', [
            'activities' => $query->paginate(15)->withQueryString(),
            'types' => $this->activityTypes(),
            'owners' => $this->ownerOptions($request->user()),
            'leads' => $this->leadOptions($request->user()),
            'customers' => $this->customerOptions($request->user()),
            'contacts' => $this->contactOptions($request->user()),
            'opportunities' => $this->opportunityOptions($request->user()),
            'statuses' => Activity::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('activities.create');

        $activity = new Activity([
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
            'assigned_user_id' => $request->user()->id,
        ]);

        if ($request->filled('lead_id')) {
            $lead = Lead::visibleTo($request->user())->findOrFail($request->integer('lead_id'));
            $activity->related_type = Lead::class;
            $activity->related_id = $lead->id;
        } elseif ($request->filled('customer_id')) {
            $customer = Customer::visibleTo($request->user())->findOrFail($request->integer('customer_id'));
            $activity->related_type = Customer::class;
            $activity->related_id = $customer->id;
        } elseif ($request->filled('contact_id')) {
            $contact = Contact::visibleTo($request->user())->findOrFail($request->integer('contact_id'));
            $activity->related_type = Contact::class;
            $activity->related_id = $contact->id;
        } elseif ($request->filled('opportunity_id')) {
            $opportunity = Opportunity::visibleTo($request->user())->findOrFail($request->integer('opportunity_id'));
            $activity->related_type = Opportunity::class;
            $activity->related_id = $opportunity->id;
        }

        return view('activities.create', $this->formData($activity, $request->user()));
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('activities.create');

        $validated = $this->validatedActivity($request);
        $this->abortIfCannotUseRelatedRecord($request, $validated);
        $this->abortIfCannotAssignTo($request->user(), (int) $validated['assigned_user_id']);

        $activity = Activity::create($this->activityPayload($validated, $request->user()->id));
        $this->syncLeadFollowUp($activity);
        $audit->created($activity, 'activity.created', 'Activity created.', $request->user(), $request);

        return redirect()->route('activities.show', $activity)->with('status', 'Activity created.');
    }

    public function show(Request $request, Activity $activity)
    {
        $this->authorize('activities.view');
        $this->abortIfCannotAccessActivity($request->user(), $activity);

        return view('activities.show', [
            'activity' => $activity->load(['type', 'assignedUser', 'createdBy', 'updatedBy', 'related']),
            'nextFollowUpTypes' => $this->activityTypes(),
            'owners' => $this->ownerOptions($request->user()),
        ]);
    }

    public function edit(Request $request, Activity $activity)
    {
        $this->authorize('activities.edit');
        $this->abortIfCannotAccessActivity($request->user(), $activity);

        return view('activities.edit', $this->formData($activity, $request->user()));
    }

    public function update(Request $request, Activity $activity, AuditService $audit)
    {
        $this->authorize('activities.edit');
        $this->abortIfCannotAccessActivity($request->user(), $activity);

        $validated = $this->validatedActivity($request);
        $this->abortIfCannotUseRelatedRecord($request, $validated);
        $this->abortIfCannotAssignTo($request->user(), (int) $validated['assigned_user_id']);
        $before = $activity->getAttributes();

        $activity->fill($this->activityPayload($validated, $request->user()->id, false))->save();
        $this->syncLeadFollowUp($activity);
        $audit->updated($activity, $before, 'activity.updated', 'Activity updated.', $request->user(), $request);

        return redirect()->route('activities.show', $activity)->with('status', 'Activity updated.');
    }

    public function complete(Request $request, Activity $activity, AuditService $audit)
    {
        $this->authorize('activities.complete');
        $this->abortIfCannotAccessActivity($request->user(), $activity);

        $validated = $request->validate([
            'outcome' => ['required', 'string', 'max:5000'],
            'completion_notes' => ['nullable', 'string', 'max:5000'],
            'next_action' => ['nullable', 'string', 'max:5000'],
            'next_follow_up_at' => ['nullable', 'date'],
            'next_follow_up_type_id' => ['nullable', Rule::exists('crm_master_values', 'id')->where('type', 'activity_type')->where('is_active', true)],
            'next_follow_up_subject' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($activity, $request, $validated, $audit) {
            $before = $activity->getAttributes();
            $activity->fill([
                'status' => 'completed',
                'completed_at' => now(),
                'outcome' => $validated['outcome'],
                'completion_notes' => $validated['completion_notes'] ?? null,
                'next_action' => $validated['next_action'] ?? null,
                'updated_by_id' => $request->user()->id,
            ])->save();
            $audit->updated($activity, $before, 'activity.completed', 'Activity completed.', $request->user(), $request);

            if (! empty($validated['next_follow_up_at'])) {
                $nextTypeId = $validated['next_follow_up_type_id'] ?? $activity->activity_type_id;
                $next = Activity::create([
                    'activity_type_id' => $nextTypeId,
                    'subject' => ($validated['next_follow_up_subject'] ?? null) ?: 'Follow up: ' . $activity->subject,
                    'description' => $validated['next_action'] ?? null,
                    'related_type' => $activity->related_type,
                    'related_id' => $activity->related_id,
                    'assigned_user_id' => $activity->assigned_user_id,
                    'created_by_id' => $request->user()->id,
                    'updated_by_id' => $request->user()->id,
                    'priority' => $activity->priority,
                    'status' => 'pending',
                    'due_at' => $validated['next_follow_up_at'],
                ]);

                $this->syncLeadFollowUp($next);
                $audit->created($next, 'activity.created', 'Next follow-up activity created.', $request->user(), $request);
            }
        });

        return redirect()->route('activities.show', $activity)->with('status', 'Activity completed.');
    }

    private function formData(Activity $activity, User $user): array
    {
        return [
            'activity' => $activity,
            'types' => $this->activityTypes(),
            'owners' => $this->ownerOptions($user),
            'leads' => $this->leadOptions($user),
            'customers' => $this->customerOptions($user),
            'contacts' => $this->contactOptions($user),
            'opportunities' => $this->opportunityOptions($user),
            'priorities' => Activity::PRIORITIES,
            'statuses' => Activity::STATUSES,
        ];
    }

    private function validatedActivity(Request $request): array
    {
        return $request->validate([
            'activity_type_id' => ['required', Rule::exists('crm_master_values', 'id')->where('type', 'activity_type')->where('is_active', true)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'opportunity_id' => ['nullable', 'integer', 'exists:opportunities,id'],
            'assigned_user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::in(Activity::PRIORITIES)],
            'status' => ['required', Rule::in(Activity::STATUSES)],
            'due_at' => ['required', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'outcome' => ['nullable', 'string', 'max:5000'],
            'completion_notes' => ['nullable', 'string', 'max:5000'],
            'next_action' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function activityPayload(array $validated, int $userId, bool $creating = true): array
    {
        [$relatedType, $relatedId] = $this->relatedPayload($validated);

        $payload = [
            'activity_type_id' => $validated['activity_type_id'],
            'subject' => $validated['subject'],
            'description' => $validated['description'] ?? null,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'assigned_user_id' => $validated['assigned_user_id'],
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'due_at' => $validated['due_at'],
            'reminder_at' => $validated['reminder_at'] ?? null,
            'outcome' => $validated['outcome'] ?? null,
            'completion_notes' => $validated['completion_notes'] ?? null,
            'next_action' => $validated['next_action'] ?? null,
            'updated_by_id' => $userId,
        ];

        if ($creating) {
            $payload['created_by_id'] = $userId;
        }

        if (($validated['status'] ?? '') !== 'completed') {
            $payload['completed_at'] = null;
        } elseif ($creating) {
            $payload['completed_at'] = now();
        }

        return $payload;
    }

    private function abortIfCannotAccessActivity(User $user, Activity $activity): void
    {
        abort_unless(Activity::whereKey($activity->id)->visibleTo($user)->exists(), 403);
    }

    private function abortIfCannotUseRelatedRecord(Request $request, array $validated): void
    {
        $selected = array_filter([
            'lead_id' => $validated['lead_id'] ?? null,
            'customer_id' => $validated['customer_id'] ?? null,
            'contact_id' => $validated['contact_id'] ?? null,
            'opportunity_id' => $validated['opportunity_id'] ?? null,
        ]);

        abort_if(count($selected) > 1, 422, 'Choose only one related record.');

        if (! empty($validated['lead_id'])) {
            abort_unless(Lead::whereKey($validated['lead_id'])->visibleTo($request->user())->exists(), 403);
        }

        if (! empty($validated['customer_id'])) {
            abort_unless(Customer::whereKey($validated['customer_id'])->visibleTo($request->user())->exists(), 403);
        }

        if (! empty($validated['contact_id'])) {
            abort_unless(Contact::whereKey($validated['contact_id'])->visibleTo($request->user())->exists(), 403);
        }

        if (! empty($validated['opportunity_id'])) {
            abort_unless(Opportunity::whereKey($validated['opportunity_id'])->visibleTo($request->user())->exists(), 403);
        }
    }

    private function relatedPayload(array $validated): array
    {
        if (! empty($validated['lead_id'])) {
            return [Lead::class, $validated['lead_id']];
        }

        if (! empty($validated['customer_id'])) {
            return [Customer::class, $validated['customer_id']];
        }

        if (! empty($validated['contact_id'])) {
            return [Contact::class, $validated['contact_id']];
        }

        if (! empty($validated['opportunity_id'])) {
            return [Opportunity::class, $validated['opportunity_id']];
        }

        return [null, null];
    }

    private function abortIfCannotAssignTo(User $user, int $assignedUserId): void
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return;
        }

        $allowed = $user->reportingTreeUserIds();
        $allowed[] = $user->id;

        abort_unless(in_array($assignedUserId, $allowed, true), 403);
    }

    private function syncLeadFollowUp(Activity $activity): void
    {
        if ($activity->related_type !== Lead::class || ! $activity->related_id || $activity->status !== 'pending') {
            return;
        }

        Lead::whereKey($activity->related_id)->update(['next_follow_up_at' => $activity->due_at]);
    }

    private function activityTypes()
    {
        return CrmMasterValue::where('type', 'activity_type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
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

    private function leadOptions(User $user)
    {
        return Lead::visibleTo($user)->orderBy('name')->get();
    }

    private function customerOptions(User $user)
    {
        return Customer::visibleTo($user)->orderBy('name')->get();
    }

    private function contactOptions(User $user)
    {
        return Contact::with('customer')->visibleTo($user)->orderBy('first_name')->orderBy('last_name')->get();
    }

    private function opportunityOptions(User $user)
    {
        return Opportunity::visibleTo($user)->orderBy('name')->get();
    }
}
