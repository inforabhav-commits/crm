<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\WebhookInboxEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CallLogUpsertService
{
    public function __construct(private CallLogMatcher $matcher, private MissedCallAutomationService $missedCallAutomation)
    {
    }

    public function apply(WebhookInboxEntry $entry): ?CallLog
    {
        $event = $entry->normalized_payload;
        if (! is_array($event) || ($event['provider'] ?? null) !== 'justcall') {
            return null;
        }

        $externalCallId = $this->string($event['external_call_id'] ?? null);
        $externalEventId = $this->string($event['external_event_id'] ?? $entry->external_id);
        if (! $externalCallId && ! $externalEventId) {
            return null;
        }

        return DB::transaction(function () use ($entry, $event, $externalCallId, $externalEventId) {
            $callLog = $externalCallId
                ? CallLog::firstOrNew(['provider' => 'justcall', 'external_call_id' => $externalCallId])
                : CallLog::firstOrNew(['provider' => 'justcall', 'external_event_id' => $externalEventId]);

            $incomingEventAt = $this->eventAt($event, $entry);
            $matching = $callLog->exists ? [] : $this->matcher->match($this->string($event['customer_number_comparison'] ?? null));

            $payload = [
                'external_event_id' => $externalEventId ?: $callLog->external_event_id,
                'direction' => $this->direction($event['direction'] ?? null, $callLog->direction),
                'status' => $this->status($callLog->status, $event['event_type'] ?? null, $event['status'] ?? null),
                'user_id' => $this->integer($event['resolved_crm_user_id'] ?? null) ?: $callLog->user_id,
                'agent_external_id' => $this->string($event['agent_external_id'] ?? null) ?: $callLog->agent_external_id,
                'from_number' => $this->string($event['from_number'] ?? null) ?: $callLog->from_number,
                'to_number' => $this->string($event['to_number'] ?? null) ?: $callLog->to_number,
                'customer_number' => $this->string($event['customer_number'] ?? null) ?: $callLog->customer_number,
                'customer_number_normalized' => $this->string($event['customer_number_comparison'] ?? null) ?: $callLog->customer_number_normalized,
                'started_at' => $this->earliest($callLog->started_at, $this->date($event['started_at'] ?? null)),
                'answered_at' => $this->earliest($callLog->answered_at, $this->date($event['answered_at'] ?? null)),
                'ended_at' => $this->latest($callLog->ended_at, $this->date($event['ended_at'] ?? null)),
                'duration_seconds' => max((int) ($callLog->duration_seconds ?? 0), (int) ($event['duration_seconds'] ?? 0)) ?: null,
                'provider_disposition' => $this->string($event['disposition'] ?? null) ?: $callLog->provider_disposition,
                'crm_disposition' => $callLog->crm_disposition,
                'provider_notes' => $this->string($event['notes'] ?? null) ?: $callLog->provider_notes,
                'crm_notes' => $callLog->crm_notes,
                'disposition' => $callLog->crm_disposition ?: $this->string($event['disposition'] ?? null) ?: $callLog->disposition,
                'notes' => $callLog->crm_notes ?: $this->string($event['notes'] ?? null) ?: $callLog->notes,
                'recording_reference' => $this->string($event['recording_reference'] ?? null) ?: $callLog->recording_reference,
                'recording_url' => $this->string($event['recording_url'] ?? null) ?: $callLog->recording_url,
                'recording_status' => $this->recordingStatus($callLog, $event),
                'recording_duration_seconds' => $this->integer($event['recording_duration_seconds'] ?? null) ?: $callLog->recording_duration_seconds,
                'recording_fetched_at' => ($this->string($event['recording_url'] ?? null) || $this->string($event['recording_reference'] ?? null)) ? now() : $callLog->recording_fetched_at,
                'screen_pop_expires_at' => $this->screenPopExpiresAt($callLog, $event),
                'last_event_at' => $this->latest($callLog->last_event_at, $incomingEventAt),
            ] + $matching;

            if (! $callLog->exists && ! $externalCallId) {
                $payload['external_call_id'] = null;
            }

            $callLog->fill($payload)->save();
            $entry->forceFill(['call_log_id' => $callLog->id])->save();
            $callLog = $callLog->refresh();

            $this->missedCallAutomation->handle($callLog, $event);

            return $callLog->refresh();
        });
    }

    private function status(?string $current, ?string $eventType, mixed $sourceStatus): ?string
    {
        $incoming = $this->string($sourceStatus) ?: match ($eventType) {
            'call.initiated' => 'initiated',
            'call.ringing' => 'ringing',
            'call.answered' => 'answered',
            'call.completed' => 'completed',
            'call.missed' => 'missed',
            default => null,
        };

        if (! $incoming) {
            return $current;
        }

        return $this->rank($incoming) >= $this->rank($current) ? $incoming : $current;
    }

    private function rank(?string $status): int
    {
        return match ($status) {
            'initiated', 'ringing' => 10,
            'answered' => 20,
            'missed' => 30,
            'completed' => 40,
            default => $status ? 15 : 0,
        };
    }

    private function eventAt(array $event, WebhookInboxEntry $entry): ?Carbon
    {
        return $this->latest(
            $this->latest($this->date($event['started_at'] ?? null), $this->date($event['answered_at'] ?? null)),
            $this->latest($this->date($event['ended_at'] ?? null), $entry->normalized_at ?: $entry->received_at)
        );
    }

    private function direction(mixed $incoming, ?string $current): string
    {
        $incoming = $this->string($incoming);
        if (in_array($incoming, ['inbound', 'outbound'], true)) {
            return $incoming;
        }

        return $current ?: 'unknown';
    }

    private function earliest($current, $incoming): mixed
    {
        if (! $current) {
            return $incoming;
        }

        if (! $incoming) {
            return $current;
        }

        return $incoming->lt($current) ? $incoming : $current;
    }

    private function latest($current, $incoming): mixed
    {
        if (! $current) {
            return $incoming;
        }

        if (! $incoming) {
            return $current;
        }

        return $incoming->gt($current) ? $incoming : $current;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function string(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : str($value)->limit(500)->toString();
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function recordingStatus(CallLog $callLog, array $event): string
    {
        if ($this->string($event['recording_url'] ?? null) || $this->string($event['recording_reference'] ?? null)) {
            return 'available';
        }

        return $callLog->recording_status ?: 'unavailable';
    }

    private function screenPopExpiresAt(CallLog $callLog, array $event): mixed
    {
        if (($event['direction'] ?? null) !== 'inbound') {
            return $callLog->screen_pop_expires_at;
        }

        return match ($event['event_type'] ?? null) {
            'call.ringing', 'call.answered' => now()->addMinutes(5),
            'call.completed', 'call.missed' => now(),
            default => $callLog->screen_pop_expires_at,
        };
    }
}
