<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Integrations\JustCall\JustCallClickToCallService;
use App\Services\PhonePrivacyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('customers.view');

        $filters = $request->only(['search', 'owner', 'status', 'industry', 'from_date', 'to_date']);
        $query = Customer::with(['owner'])
            ->visibleTo($request->user())
            ->latest();

        app(\App\Services\CustomerDateFilter::class)->apply($query, $request);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($subQuery) use ($search, $request) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('external_customer_id', 'like', "%{$search}%")
                    ->orWhere('software', 'like', "%{$search}%")
                    ->orWhere('license_number', 'like', "%{$search}%")
                    ->orWhere('product_number', 'like', "%{$search}%");

                if (app(PhonePrivacyService::class)->canViewFullPhone($request->user())) {
                    $subQuery->orWhere('phone', 'like', "%{$search}%");
                }
            });
        }

        if (! empty($filters['owner'])) {
            $query->where('owner_id', $filters['owner']);
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        if (! empty($filters['industry'])) {
            $query->where('industry', 'like', "%{$filters['industry']}%");
        }

        return view('customers.index', [
            'customers' => $query->paginate(15)->withQueryString(),
            'owners' => $this->ownerOptions($request->user()),
            'filters' => $filters,
            'canCallCustomers' => $request->user()->can('calls.initiate') && $request->user()->is_active,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('customers.create');

        $customer = new Customer([
            'owner_id' => $request->user()->id,
            'is_active' => true,
        ]);

        if ($request->filled('lead_id')) {
            $lead = Lead::visibleTo($request->user())->findOrFail($request->integer('lead_id'));
            $customer->fill([
                'name' => $lead->company ?: $lead->name,
                'company' => $lead->company,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'owner_id' => $lead->owner_id ?: $request->user()->id,
                'converted_from_lead_id' => $lead->id,
                'notes' => $lead->notes,
            ]);
        }

        return view('customers.create', $this->formData($customer, $request->user()));
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('customers.create');

        $validated = $this->validatedCustomer($request);
        if (! app(PhonePrivacyService::class)->canViewFullPhone($request->user()) && trim((string) ($validated['phone'] ?? '')) === '') {
            $validated['phone'] = null;
        }
        $this->abortIfCannotAssignTo($request->user(), (int) $validated['owner_id']);
        $this->abortIfCannotUseLead($request, $validated['converted_from_lead_id'] ?? null);

        $customer = Customer::create(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]));
        $audit->created($customer, 'customer.created', 'Customer created.', $request->user(), $request);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer created.');
    }

    public function show(Request $request, Customer $customer, JustCallClickToCallService $clickToCall)
    {
        $this->authorize('customers.view');
        $this->abortIfCannotAccessCustomer($request->user(), $customer);

        $customer->load([
            'owner',
            'contacts.activities.type',
            'contacts.activities.assignedUser',
            'activities.type',
            'activities.assignedUser',
            'convertedFromLead.status',
            'convertedFromLead.source',
        ]);

        $contactIds = $customer->contacts->pluck('id')->all();
        $timelineActivities = Activity::with(['type', 'assignedUser', 'related'])
            ->visibleTo($request->user())
            ->where(function ($query) use ($customer, $contactIds) {
                $query->where(function ($customerQuery) use ($customer) {
                    $customerQuery->where('related_type', Customer::class)
                        ->where('related_id', $customer->id);
                });

                if ($contactIds) {
                    $query->orWhere(function ($contactQuery) use ($contactIds) {
                        $contactQuery->where('related_type', Contact::class)
                            ->whereIn('related_id', $contactIds);
                    });
                }
            })
            ->orderByDesc('due_at')
            ->get();

        $pendingActivities = $timelineActivities->where('status', 'pending');
        $callLogs = CallLog::with(['user', 'lead', 'customer', 'contact', 'opportunity'])
            ->visibleTo($request->user())
            ->where(function ($query) use ($customer, $contactIds) {
                $query->where('customer_id', $customer->id);

                if ($contactIds) {
                    $query->orWhereIn('contact_id', $contactIds);
                }
            })
            ->latest('last_event_at')
            ->take(10)
            ->get();

        return view('customers.show', [
            'customer' => $customer,
            'primaryContact' => $customer->contacts->firstWhere('is_primary', true) ?: $customer->contacts->first(),
            'timelineActivities' => $timelineActivities,
            'recentActivities' => $timelineActivities->take(10),
            'upcomingActivities' => $pendingActivities->filter(fn (Activity $activity) => ! $activity->is_overdue)->sortBy('due_at')->take(5),
            'overdueActivities' => $pendingActivities->filter(fn (Activity $activity) => $activity->is_overdue)->sortBy('due_at')->take(5),
            'callLogs' => $callLogs,
            'summary' => [
                'contacts' => $customer->contacts->count(),
                'active_contacts' => $customer->contacts->where('is_active', true)->count(),
                'activities' => $timelineActivities->count(),
                'pending_activities' => $pendingActivities->count(),
                'calls' => $callLogs->count(),
            ],
            'activityTypes' => $this->activityTypes(),
            'canShowCallAction' => $clickToCall->canShowFor($customer),
        ]);
    }

    public function edit(Request $request, Customer $customer)
    {
        $this->authorize('customers.edit');
        $this->abortIfCannotAccessCustomer($request->user(), $customer);

        return view('customers.edit', $this->formData($customer, $request->user()));
    }

    public function update(Request $request, Customer $customer, AuditService $audit)
    {
        $this->authorize('customers.edit');
        $this->abortIfCannotAccessCustomer($request->user(), $customer);

        $validated = $this->validatedCustomer($request);
        $this->abortIfCannotAssignTo($request->user(), (int) $validated['owner_id']);
        $this->abortIfCannotUseLead($request, $validated['converted_from_lead_id'] ?? null);
        $before = $customer->getAttributes();

        $customer->fill(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
            'updated_by_id' => $request->user()->id,
        ]))->save();
        $audit->updated($customer, $before, 'customer.updated', 'Customer updated.', $request->user(), $request);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    public function destroy(Request $request, Customer $customer, AuditService $audit)
    {
        $this->authorize('customers.delete');
        $this->abortIfCannotAccessCustomer($request->user(), $customer);

        $audit->log('customer.deleted', $customer, 'Customer deleted.', $customer->getAttributes(), null, $request->user(), $request);
        $customer->delete();

        return redirect()->route('customers.index')->with('status', 'Customer deleted.');
    }

    private function formData(Customer $customer, User $user): array
    {
        return [
            'customer' => $customer,
            'owners' => $this->ownerOptions($user),
            'leads' => Lead::visibleTo($user)->orderBy('name')->get(),
        ];
    }

    private function validatedCustomer(Request $request): array
    {
        return $request->validate([
            'external_customer_id' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'sale_date' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:255'],
            'software' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'product_number' => ['nullable', 'string', 'max:255'],
            'file_password' => ['nullable', 'string', 'max:5000'],
            'cloud_customer' => ['nullable', 'string', 'max:255'],
            'customer_user_id' => ['nullable', 'string', 'max:255'],
            'customer_password' => ['nullable', 'string', 'max:5000'],
            'issue' => ['nullable', 'string', 'max:5000'],
            'sale_type' => ['nullable', 'string', 'max:255'],
            'no_of_cases' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', 'string', 'max:255'],
            'last_4' => ['nullable', 'string', 'max:4'],
            'card_type' => ['nullable', 'string', 'max:255'],
            'end' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'converted_from_lead_id' => ['nullable', 'integer', 'exists:leads,id'],
        ]);
    }

    private function abortIfCannotAccessCustomer(User $user, Customer $customer): void
    {
        abort_unless(Customer::whereKey($customer->id)->visibleTo($user)->exists(), 403);
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

    private function abortIfCannotUseLead(Request $request, $leadId): void
    {
        if (! $leadId) {
            return;
        }

        abort_unless(Lead::whereKey($leadId)->visibleTo($request->user())->exists(), 403);
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

    private function activityTypes()
    {
        return \App\Models\CrmMasterValue::where('type', 'activity_type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
