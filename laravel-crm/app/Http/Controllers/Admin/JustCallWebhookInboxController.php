<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookInboxEntry;
use App\Services\Integrations\JustCall\JustCallWebhookInboxProcessor;
use Illuminate\Http\Request;

class JustCallWebhookInboxController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('justcall.view');

        return view('admin.justcall-settings.webhook-inbox', [
            'entries' => WebhookInboxEntry::where('provider', 'justcall')->latest('received_at')->paginate(20),
        ]);
    }

    public function processPending(JustCallWebhookInboxProcessor $processor)
    {
        $this->authorize('justcall.manage');

        $count = $processor->processPending();

        return redirect()
            ->route('admin.justcall-webhooks.index')
            ->with('status', $count.' pending JustCall webhook(s) processed.');
    }
}
