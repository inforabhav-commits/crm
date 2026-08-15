<?php

namespace App\Services\Integrations\JustCall;

use App\Models\WebhookInboxEntry;
use App\Services\CallLogUpsertService;

class JustCallWebhookInboxProcessor
{
    public function __construct(private JustCallCallEventNormalizer $normalizer, private CallLogUpsertService $callLogUpsert)
    {
    }

    public function process(WebhookInboxEntry $entry): WebhookInboxEntry
    {
        $entry->attempt_count = (int) $entry->attempt_count + 1;

        try {
            if ($entry->provider !== 'justcall') {
                return $this->markUnsupported($entry, 'Unsupported webhook provider.');
            }

            $normalized = $this->normalizer->normalize($entry);
            if ($normalized === null) {
                return $this->markUnsupported($entry, 'Unsupported JustCall event type.');
            }

            $entry->forceFill([
                'normalized_payload' => $normalized,
                'normalized_event_type' => $normalized['event_type'] ?? null,
                'normalized_at' => now(),
                'processing_status' => 'normalized',
                'processed_at' => now(),
                'failure_summary' => null,
            ])->save();

            $this->callLogUpsert->apply($entry->refresh());
        } catch (\Throwable $exception) {
            $entry->forceFill([
                'processing_status' => 'failed',
                'processed_at' => now(),
                'failure_summary' => str($exception->getMessage())->limit(255)->toString(),
            ])->save();
        }

        return $entry->refresh();
    }

    public function processPending(int $limit = 50): int
    {
        $count = 0;

        WebhookInboxEntry::query()
            ->where('provider', 'justcall')
            ->where('processing_status', 'pending')
            ->oldest('received_at')
            ->limit($limit)
            ->get()
            ->each(function (WebhookInboxEntry $entry) use (&$count) {
                $this->process($entry);
                $count++;
            });

        return $count;
    }

    private function markUnsupported(WebhookInboxEntry $entry, string $reason): WebhookInboxEntry
    {
        $entry->forceFill([
            'normalized_payload' => null,
            'normalized_event_type' => null,
            'normalized_at' => null,
            'processing_status' => 'unsupported',
            'processed_at' => now(),
            'failure_summary' => $reason,
        ])->save();

        return $entry->refresh();
    }
}
