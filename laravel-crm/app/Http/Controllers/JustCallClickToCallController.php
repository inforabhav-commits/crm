<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\Integrations\JustCall\JustCallClickToCallService;
use Illuminate\Http\Request;

class JustCallClickToCallController extends Controller
{
    public function lead(Request $request, Lead $lead, JustCallClickToCallService $clickToCall)
    {
        $this->authorize('calls.initiate');
        abort_unless($request->user()->can('leads.view') && Lead::whereKey($lead->id)->visibleTo($request->user())->exists(), 403);

        return $this->launch($request, $lead, $clickToCall);
    }

    public function customer(Request $request, Customer $customer, JustCallClickToCallService $clickToCall)
    {
        $this->authorize('calls.initiate');
        abort_unless($request->user()->can('customers.view') && Customer::whereKey($customer->id)->visibleTo($request->user())->exists(), 403);

        return $this->launch($request, $customer, $clickToCall);
    }

    public function contact(Request $request, Contact $contact, JustCallClickToCallService $clickToCall)
    {
        $this->authorize('calls.initiate');
        abort_unless($request->user()->can('contacts.view') && Contact::whereKey($contact->id)->visibleTo($request->user())->exists(), 403);

        return $this->launch($request, $contact, $clickToCall);
    }

    private function launch(Request $request, $record, JustCallClickToCallService $clickToCall)
    {
        $result = $clickToCall->launch($request->user(), $record, $request);

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->away($result['url']);
    }
}
