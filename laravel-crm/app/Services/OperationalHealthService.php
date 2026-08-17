<?php

namespace App\Services;

use App\Models\WebhookInboxEntry;
use App\Models\WorkflowExecution;
use App\Services\Integrations\JustCall\JustCallClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OperationalHealthService
{
    public function __construct(private JustCallClient $justCall)
    {
    }

    public function check(): array
    {
        return [
            'database' => $this->database(),
            'storage' => $this->storage(),
            'webhooks' => $this->webhooks(),
            'justcall' => $this->justCall(),
            'workflows' => $this->workflows(),
        ];
    }

    private function database(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return ['ok' => true, 'label' => 'Database', 'message' => 'Connected.'];
        } catch (Throwable) {
            return ['ok' => false, 'label' => 'Database', 'message' => 'Database connectivity check failed.'];
        }
    }

    private function storage(): array
    {
        $path = storage_path('app');
        $ok = is_dir($path) && is_writable($path);

        return [
            'ok' => $ok,
            'label' => 'Storage',
            'message' => $ok ? 'Writable.' : 'Storage directory is unavailable or not writable.',
        ];
    }

    private function webhooks(): array
    {
        try {
            $pending = WebhookInboxEntry::where('provider', 'justcall')->where('processing_status', 'pending')->count();
            $failed = WebhookInboxEntry::where('provider', 'justcall')->whereIn('processing_status', ['failed', 'unsupported'])->count();
            $ok = $pending === 0 && $failed === 0;

            return [
                'ok' => $ok,
                'label' => 'JustCall webhook inbox',
                'message' => $ok ? 'No pending or failed entries.' : $pending.' pending, '.$failed.' failed/unsupported.',
                'pending' => $pending,
                'failed' => $failed,
            ];
        } catch (Throwable) {
            return ['ok' => false, 'label' => 'JustCall webhook inbox', 'message' => 'Webhook health check failed.'];
        }
    }

    private function justCall(): array
    {
        $status = $this->justCall->status();
        $ok = ! $status['enabled'] || ($status['credentials_configured'] && $status['webhook_secret_configured']);

        return [
            'ok' => $ok,
            'label' => 'JustCall configuration',
            'message' => $ok ? 'Configuration is ready or integration is disabled.' : 'Enabled integration is missing required credentials or webhook secret.',
            'enabled' => $status['enabled'],
            'credentials_configured' => $status['credentials_configured'],
            'webhook_secret_configured' => $status['webhook_secret_configured'],
        ];
    }

    private function workflows(): array
    {
        try {
            $failed = WorkflowExecution::where('status', 'failed')->where('created_at', '>=', now()->subDay())->count();

            return [
                'ok' => $failed === 0,
                'label' => 'Workflow executions',
                'message' => $failed === 0 ? 'No failed executions in the last 24 hours.' : $failed.' failed execution(s) in the last 24 hours.',
                'failed_last_24_hours' => $failed,
            ];
        } catch (Throwable) {
            return ['ok' => false, 'label' => 'Workflow executions', 'message' => 'Workflow health check failed.'];
        }
    }
}
