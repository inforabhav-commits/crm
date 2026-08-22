<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\AuditService;
use App\Services\PhonePrivacyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class LeadConversionController extends Controller
{
    public function qualify(Request $request, Lead $lead, AuditService $audit)
    {
        $this->authorize('leads.qualify');
        $this->abortIfCannotAccessLead($request, $lead);

        $validated = $request->validate([
            'qualification_status' => ['required', Rule::in(['working', 'qualified', 'unqualified', 'disqualified'])],
            'qualification_need' => ['required_if:qualification_status,qualified', 'nullable', 'string', 'max:5000'],
            'qualification_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'qualification_authority' => ['required_if:qualification_status,qualified', 'nullable', 'string', 'max:255'],
            'qualification_timeline' => ['required_if:qualification_status,qualified', 'nullable', 'string', 'max:255'],
            'qualification_interest_level' => ['required_if:qualification_status,qualified', 'nullable', Rule::in(['low', 'medium', 'high'])],
            'qualification_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $before = $lead->getAttributes();

        $lead->fill(array_merge($validated, [
            'qualified_at' => $validated['qualification_status'] === 'qualified' ? now() : null,
            'qualified_by_id' => $validated['qualification_status'] === 'qualified' ? $request->user()->id : null,
            'updated_by_id' => $request->user()->id,
        ]))->save();
        $audit->updated($lead, $before, 'lead.qualified', 'Lead qualification updated.', $request->user(), $request);

        return redirect()->route('leads.show', $lead)->with('status', 'Lead qualification updated.');
    }

    public function convert(Request $request, Lead $lead, AuditService $audit)
    {
        $this->authorize('leads.convert');
        $this->abortIfCannotAccessLead($request, $lead);

        if ($lead->converted_at) {
            return back()->withErrors(['conversion' => 'This lead has already been converted.']);
        }

        if (! $lead->isQualifiedForConversion()) {
            return back()->withErrors(['conversion' => 'Complete lead qualification before conversion.']);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'contact_first_name' => ['required', 'string', 'max:255'],
            'contact_last_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'opportunity_name' => ['required', 'string', 'max:255'],
            'opportunity_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'expected_close_date' => ['nullable', 'date'],
        ]);
        if (! app(PhonePrivacyService::class)->canViewFullPhone($request->user())) {
            if (trim((string) ($validated['customer_phone'] ?? '')) === '') {
                $validated['customer_phone'] = $lead->phone;
            }
            if (trim((string) ($validated['contact_phone'] ?? '')) === '') {
                $validated['contact_phone'] = $lead->phone;
            }
        }

        try {
            DB::transaction(function () use ($request, $lead, $validated, $audit) {
                $before = $lead->getAttributes();
                $customer = $this->findOrCreateCustomer($request, $lead, $validated);
                $contact = $this->findOrCreateContact($request, $lead, $customer, $validated);
                $opportunity = $this->createOpportunity($request, $lead, $customer, $contact, $validated);
                $convertedStatus = $this->convertedLeadStatus();

                $lead->fill([
                    'lead_status_id' => $convertedStatus->id,
                    'converted_at' => now(),
                    'converted_by_id' => $request->user()->id,
                    'converted_customer_id' => $customer->id,
                    'converted_contact_id' => $contact->id,
                    'converted_opportunity_id' => $opportunity->id,
                    'updated_by_id' => $request->user()->id,
                ])->save();

                $audit->updated($lead, $before, 'lead.converted', 'Lead converted to customer, contact, and opportunity.', $request->user(), $request);
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['conversion' => 'Lead conversion failed. No records were changed.']);
        }

        return redirect()->route('leads.show', $lead)->with('status', 'Lead converted.');
    }

    private function findOrCreateCustomer(Request $request, Lead $lead, array $validated): Customer
    {
        $customer = $this->findMatchingCustomer($request, $validated['customer_email'] ?? null, $validated['customer_phone'] ?? null);

        if ($customer) {
            if (! $customer->converted_from_lead_id) {
                $customer->forceFill(['converted_from_lead_id' => $lead->id])->save();
            }

            return $customer;
        }

        return Customer::create([
            'name' => $validated['customer_name'],
            'company' => $validated['customer_company'] ?? $lead->company,
            'email' => $validated['customer_email'] ?? $lead->email,
            'phone' => $validated['customer_phone'] ?? $lead->phone,
            'notes' => $lead->notes,
            'owner_id' => $lead->owner_id ?: $request->user()->id,
            'converted_from_lead_id' => $lead->id,
            'is_active' => true,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);
    }

    private function findOrCreateContact(Request $request, Lead $lead, Customer $customer, array $validated): Contact
    {
        $contact = $this->findMatchingContact($customer, $validated['contact_email'] ?? null, $validated['contact_phone'] ?? null);

        if ($contact) {
            if (! $contact->source_lead_id) {
                $contact->forceFill(['source_lead_id' => $lead->id])->save();
            }

            return $contact;
        }

        return Contact::create([
            'customer_id' => $customer->id,
            'source_lead_id' => $lead->id,
            'first_name' => $validated['contact_first_name'],
            'last_name' => $validated['contact_last_name'] ?? null,
            'email' => $validated['contact_email'] ?? $lead->email,
            'phone' => $validated['contact_phone'] ?? $lead->phone,
            'is_primary' => ! $customer->contacts()->where('is_primary', true)->exists(),
            'is_active' => true,
            'notes' => $lead->qualification_notes,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);
    }

    private function createOpportunity(Request $request, Lead $lead, Customer $customer, Contact $contact, array $validated): Opportunity
    {
        return Opportunity::create([
            'name' => $validated['opportunity_name'],
            'customer_id' => $customer->id,
            'contact_id' => $contact->id,
            'source_lead_id' => $lead->id,
            'stage_id' => $this->defaultOpportunityStage()->id,
            'owner_id' => $lead->owner_id ?: $request->user()->id,
            'amount' => $validated['opportunity_amount'] ?? $lead->qualification_budget,
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'notes' => $lead->qualification_need,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);
    }

    private function findMatchingCustomer(Request $request, ?string $email, ?string $phone): ?Customer
    {
        $query = Customer::visibleTo($request->user());

        if ($email) {
            $customer = (clone $query)->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
            if ($customer) {
                return $customer;
            }
        }

        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone === '') {
            return null;
        }

        return $query->get()->first(function (Customer $customer) use ($normalizedPhone) {
            return $this->normalizePhone($customer->phone) === $normalizedPhone;
        });
    }

    private function findMatchingContact(Customer $customer, ?string $email, ?string $phone): ?Contact
    {
        if ($email) {
            $contact = $customer->contacts()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
            if ($contact) {
                return $contact;
            }
        }

        $normalizedPhone = $this->normalizePhone($phone);
        if ($normalizedPhone === '') {
            return null;
        }

        return $customer->contacts->first(function (Contact $contact) use ($normalizedPhone) {
            return $this->normalizePhone($contact->phone) === $normalizedPhone;
        });
    }

    private function convertedLeadStatus(): CrmMasterValue
    {
        return CrmMasterValue::where('type', 'lead_status')
            ->where('slug', 'converted')
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function defaultOpportunityStage(): CrmMasterValue
    {
        return CrmMasterValue::where('type', 'opportunity_stage')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->firstOrFail();
    }

    private function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }

    private function abortIfCannotAccessLead(Request $request, Lead $lead): void
    {
        abort_unless(Lead::whereKey($lead->id)->visibleTo($request->user())->exists(), 403);
    }
}
