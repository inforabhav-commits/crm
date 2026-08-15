<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CallLog;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Services\AuditService;
use App\Services\Integrations\JustCall\JustCallClickToCallService;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('contacts.view');

        $filters = $request->only(['search', 'customer', 'status']);
        $query = Contact::with('customer.owner')
            ->visibleTo($request->user())
            ->latest();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['customer'])) {
            $customer = Customer::visibleTo($request->user())->findOrFail($filters['customer']);
            $query->where('customer_id', $customer->id);
        }

        if (($filters['status'] ?? '') === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('contacts.index', [
            'contacts' => $query->paginate(15)->withQueryString(),
            'customers' => Customer::visibleTo($request->user())->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('contacts.create');

        $contact = new Contact([
            'customer_id' => $request->integer('customer_id') ?: null,
            'is_active' => true,
        ]);

        if ($contact->customer_id) {
            $this->abortIfCannotAccessCustomer($request, $contact->customer_id);
        }

        return view('contacts.create', $this->formData($contact, $request));
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('contacts.create');

        $validated = $this->validatedContact($request);
        $this->abortIfCannotAccessCustomer($request, (int) $validated['customer_id']);

        $contact = Contact::create(array_merge($validated, [
            'is_primary' => $request->boolean('is_primary'),
            'is_active' => $request->boolean('is_active'),
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]));
        $audit->created($contact, 'contact.created', 'Contact created.', $request->user(), $request);

        return redirect()->route('contacts.show', $contact)->with('status', 'Contact created.');
    }

    public function show(Request $request, Contact $contact, JustCallClickToCallService $clickToCall)
    {
        $this->authorize('contacts.view');
        $this->abortIfCannotAccessContact($request, $contact);

        return view('contacts.show', [
            'contact' => $contact->load(['customer.owner', 'sourceLead', 'activities.type', 'activities.assignedUser']),
            'activityTypes' => $this->activityTypes(),
            'callLogs' => CallLog::with(['user', 'lead', 'customer', 'contact', 'opportunity'])
                ->visibleTo($request->user())
                ->where('contact_id', $contact->id)
                ->latest('last_event_at')
                ->take(10)
                ->get(),
            'canShowCallAction' => $clickToCall->canShowFor($contact),
        ]);
    }

    public function edit(Request $request, Contact $contact)
    {
        $this->authorize('contacts.edit');
        $this->abortIfCannotAccessContact($request, $contact);

        return view('contacts.edit', $this->formData($contact, $request));
    }

    public function update(Request $request, Contact $contact, AuditService $audit)
    {
        $this->authorize('contacts.edit');
        $this->abortIfCannotAccessContact($request, $contact);

        $validated = $this->validatedContact($request);
        $this->abortIfCannotAccessCustomer($request, (int) $validated['customer_id']);
        $before = $contact->getAttributes();

        $contact->fill(array_merge($validated, [
            'is_primary' => $request->boolean('is_primary'),
            'is_active' => $request->boolean('is_active'),
            'updated_by_id' => $request->user()->id,
        ]))->save();
        $audit->updated($contact, $before, 'contact.updated', 'Contact updated.', $request->user(), $request);

        return redirect()->route('contacts.show', $contact)->with('status', 'Contact updated.');
    }

    private function formData(Contact $contact, Request $request): array
    {
        return [
            'contact' => $contact,
            'customers' => Customer::visibleTo($request->user())->orderBy('name')->get(),
        ];
    }

    private function validatedContact(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function abortIfCannotAccessContact(Request $request, Contact $contact): void
    {
        abort_unless(Contact::whereKey($contact->id)->visibleTo($request->user())->exists(), 403);
    }

    private function abortIfCannotAccessCustomer(Request $request, int $customerId): void
    {
        abort_unless(Customer::whereKey($customerId)->visibleTo($request->user())->exists(), 403);
    }

    private function activityTypes()
    {
        return CrmMasterValue::where('type', 'activity_type')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
