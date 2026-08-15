<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;

class CallLogMatcher
{
    public function __construct(private PhoneNumberNormalizer $phoneNormalizer)
    {
    }

    public function match(?string $comparisonNumber): array
    {
        $result = $this->resolve($comparisonNumber);

        return $result['ids'];
    }

    public function resolve(?string $comparisonNumber): array
    {
        $empty = [
            'lead_id' => null,
            'customer_id' => null,
            'contact_id' => null,
            'opportunity_id' => null,
        ];

        if (! $comparisonNumber) {
            return ['state' => 'unknown', 'type' => null, 'ids' => $empty, 'count' => 0];
        }

        $contacts = $this->matchingContacts($comparisonNumber);
        if ($contacts->count() > 1) {
            return ['state' => 'ambiguous', 'type' => 'Contact', 'ids' => $empty, 'count' => $contacts->count()];
        }

        if ($contacts->count() === 1) {
            $contact = $contacts->first();

            return ['state' => 'matched', 'type' => 'Contact', 'ids' => [
                'lead_id' => $contact->source_lead_id,
                'customer_id' => $contact->customer_id,
                'contact_id' => $contact->id,
                'opportunity_id' => $this->uniqueOpportunity($contact->customer_id, $contact->id)?->id,
            ], 'count' => 1];
        }

        $customers = $this->matchingCustomers($comparisonNumber);
        if ($customers->count() > 1) {
            return ['state' => 'ambiguous', 'type' => 'Customer', 'ids' => $empty, 'count' => $customers->count()];
        }

        if ($customers->count() === 1) {
            $customer = $customers->first();

            return ['state' => 'matched', 'type' => 'Customer', 'ids' => [
                'lead_id' => $customer->converted_from_lead_id,
                'customer_id' => $customer->id,
                'contact_id' => null,
                'opportunity_id' => $this->uniqueOpportunity($customer->id, null)?->id,
            ], 'count' => 1];
        }

        $leads = $this->matchingLeads($comparisonNumber);
        if ($leads->count() > 1) {
            return ['state' => 'ambiguous', 'type' => 'Lead', 'ids' => $empty, 'count' => $leads->count()];
        }

        if ($leads->count() === 1) {
            return ['state' => 'matched', 'type' => 'Lead', 'ids' => array_merge($empty, ['lead_id' => $leads->first()->id]), 'count' => 1];
        }

        return ['state' => 'unknown', 'type' => null, 'ids' => $empty, 'count' => 0];
    }

    private function uniqueContact(string $comparisonNumber): ?Contact
    {
        $matches = $this->matchingContacts($comparisonNumber);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function matchingContacts(string $comparisonNumber)
    {
        return Contact::query()
            ->where(function ($query) {
                $query->whereNotNull('phone')->orWhereNotNull('mobile');
            })
            ->get()
            ->filter(function (Contact $contact) use ($comparisonNumber) {
                return in_array($comparisonNumber, [
                    $this->phoneNormalizer->normalize($contact->phone)['comparison'],
                    $this->phoneNormalizer->normalize($contact->mobile)['comparison'],
                ], true);
            })
            ->values();
    }

    private function uniqueCustomer(string $comparisonNumber): ?Customer
    {
        $matches = $this->matchingCustomers($comparisonNumber);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function matchingCustomers(string $comparisonNumber)
    {
        return Customer::query()
            ->whereNotNull('phone')
            ->get()
            ->filter(fn (Customer $customer) => $this->phoneNormalizer->normalize($customer->phone)['comparison'] === $comparisonNumber)
            ->values();
    }

    private function uniqueLead(string $comparisonNumber): ?Lead
    {
        $matches = $this->matchingLeads($comparisonNumber);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function matchingLeads(string $comparisonNumber)
    {
        return Lead::query()
            ->whereNotNull('phone')
            ->get()
            ->filter(fn (Lead $lead) => $this->phoneNormalizer->normalize($lead->phone)['comparison'] === $comparisonNumber)
            ->values();
    }

    private function uniqueOpportunity(int $customerId, ?int $contactId): ?Opportunity
    {
        $query = Opportunity::query()
            ->where('customer_id', $customerId)
            ->where('status', 'open');

        if ($contactId) {
            $query->where(function ($subQuery) use ($contactId) {
                $subQuery->where('contact_id', $contactId)->orWhereNull('contact_id');
            });
        }

        $matches = $query->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
