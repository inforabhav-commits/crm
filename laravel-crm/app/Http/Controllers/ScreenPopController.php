<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\CallLogMatcher;
use App\Services\PhonePrivacyService;
use Illuminate\Http\Request;

class ScreenPopController extends Controller
{
    public function current(Request $request, CallLogMatcher $matcher)
    {
        $this->authorize('calls.initiate');
        $callLog = CallLog::with(['user', 'lead.owner', 'customer.owner', 'contact.customer.owner'])
            ->where('provider', 'justcall')
            ->where('direction', 'inbound')
            ->where('user_id', $request->user()->id)
            ->whereNull('screen_pop_dismissed_at')
            ->where('screen_pop_expires_at', '>', now())
            ->whereIn('status', ['ringing', 'answered', 'completed', 'missed', 'failed'])
            ->latest('last_event_at')
            ->first();

        if (! $callLog) {
            return response()->json(['screen_pop' => null]);
        }

        return response()->json([
            'screen_pop' => $this->payload($request, $callLog, $matcher),
        ]);
    }

    public function dismiss(Request $request, CallLog $callLog)
    {
        $this->authorize('calls.initiate');
        abort_unless($callLog->user_id === $request->user()->id, 403);

        $callLog->forceFill(['screen_pop_dismissed_at' => now()])->save();

        return response()->json(['dismissed' => true]);
    }

    private function payload(Request $request, CallLog $callLog, CallLogMatcher $matcher): array
    {
        $match = $matcher->resolve($callLog->customer_number_normalized);
        $record = $this->visibleMatchedRecord($request, $callLog);
        $state = $record ? 'matched' : $match['state'];
        $phonePrivacy = app(PhonePrivacyService::class);
        if ($record && ! $phonePrivacy->canViewFullPhone($request->user())) {
            foreach (['name', 'owner', 'recent_activity'] as $field) {
                $record[$field] = $phonePrivacy->maskedText($record[$field] ?? null);
            }
        }

        if ($match['state'] === 'matched' && ! $record) {
            $state = 'restricted';
        }

        return [
            'id' => $callLog->id,
            'status' => $callLog->status,
            'masked_number' => $phonePrivacy->mask($callLog->customer_number ?: $callLog->from_number ?: $callLog->customer_number_normalized),
            'direction' => $callLog->direction,
            'notes' => $request->user()->can('calls.view') ? $phonePrivacy->maskedText($callLog->crm_notes ?: $callLog->notes) : null,
            'disposition' => $request->user()->can('calls.view') ? $phonePrivacy->maskedText($callLog->crm_disposition ?: $callLog->disposition) : null,
            'duration_seconds' => $callLog->duration_seconds,
            'ended_at' => $callLog->ended_at?->toIso8601String(),
            'history_url' => $request->user()->can('calls.view') ? route('calls.show', $callLog) : null,
            'match_state' => $state,
            'ambiguous_type' => $state === 'ambiguous' ? $match['type'] : null,
            'match_count' => $state === 'ambiguous' ? $match['count'] : null,
            'record' => $record,
            'search_url' => route('leads.index'),
            'dismiss_url' => route('screen-pop.dismiss', $callLog),
            'connected_since' => $callLog->answered_at?->toIso8601String(),
        ];
    }

    private function visibleMatchedRecord(Request $request, CallLog $callLog): ?array
    {
        if ($callLog->contact_id && Contact::whereKey($callLog->contact_id)->visibleTo($request->user())->exists()) {
            $contact = $callLog->contact ?: Contact::with('customer.owner')->find($callLog->contact_id);

            return [
                'type' => 'Contact',
                'name' => $contact->customer?->name ?: $contact->name,
                'owner' => $contact->customer?->owner?->name,
                'url' => $contact->customer ? route('customers.show', $contact->customer) : route('contacts.show', $contact),
                'open_label' => $contact->customer ? 'Open Customer' : 'Open Contact',
                'recent_activity' => $this->recentActivity(Contact::class, $contact->id),
            ];
        }

        if ($callLog->customer_id && Customer::whereKey($callLog->customer_id)->visibleTo($request->user())->exists()) {
            $customer = $callLog->customer ?: Customer::with('owner')->find($callLog->customer_id);

            return [
                'type' => 'Customer',
                'name' => $customer->name,
                'owner' => $customer->owner?->name,
                'url' => route('customers.show', $customer),
                'open_label' => 'Open Customer',
                'recent_activity' => $this->recentActivity(Customer::class, $customer->id),
            ];
        }

        if ($callLog->lead_id && Lead::whereKey($callLog->lead_id)->visibleTo($request->user())->exists()) {
            $lead = $callLog->lead ?: Lead::with('owner')->find($callLog->lead_id);

            return [
                'type' => 'Lead',
                'name' => $lead->name,
                'owner' => $lead->owner?->name,
                'url' => route('leads.show', $lead),
                'open_label' => 'Open Lead',
                'recent_activity' => $this->recentActivity(Lead::class, $lead->id),
            ];
        }

        return null;
    }

    private function recentActivity(string $type, int $id): ?string
    {
        return Activity::where('related_type', $type)
            ->where('related_id', $id)
            ->latest('due_at')
            ->value('subject');
    }
}
