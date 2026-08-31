<?php

namespace App\Services\Integrations\JustCall;

use App\Models\JustCallUserMapping;
use App\Models\WebhookInboxEntry;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JustCallCallEventNormalizer
{
    public function __construct(private PhoneNumberNormalizer $phoneNormalizer)
    {
    }

    public function normalize(WebhookInboxEntry|array $source): ?array
    {
        $payload = $source instanceof WebhookInboxEntry ? ($source->payload ?? []) : $source;
        if (! is_array($payload)) {
            return null;
        }

        $rawEventType = $this->stringValue(Arr::get($payload, 'type'));
        $eventType = $this->eventType($rawEventType);
        if ($eventType === null) {
            return null;
        }

        $data = Arr::get($payload, 'data', []);
        $data = is_array($data) ? $data : [];
        $direction = $this->direction($data, $rawEventType);
        $from = $this->phone($this->firstValue($data, ['from_number', 'from', 'caller_number', 'caller', 'caller_id']));
        $to = $this->phone($this->firstValue($data, ['to_number', 'to', 'called_number', 'dialed_number', 'justcall_number']));
        $explicitCustomer = $this->phone($this->firstValue($data, ['customer_number', 'phone_number', 'contact_number']));
        $customer = $explicitCustomer['original'] !== null
            ? $explicitCustomer
            : ($direction === 'inbound' ? $from : ($direction === 'outbound' ? $to : $this->emptyPhone()));

        $agentExternalId = $this->stringValue($this->firstValue($data, [
            'agent_id',
            'justcall_agent_id',
            'user_id',
            'agent.id',
            'agent.user_id',
            'user.id',
        ]));

        return [
            'provider' => 'justcall',
            'event_type' => $eventType,
            'external_event_id' => $this->stringValue($this->firstValue($payload, ['request_id', 'event_id', 'id'])),
            'external_call_id' => $this->stringValue($this->firstValue($data, ['call_id', 'call_sid', 'call_uuid', 'id'])),
            'direction' => $direction,
            'status' => $this->stringValue($this->firstValue($data, ['status', 'call_status'])),
            'agent_external_id' => $agentExternalId,
            'agent_name' => $this->stringValue($this->firstValue($data, ['agent_name', 'agent.name', 'user.name'])),
            'agent_email' => $this->stringValue($this->firstValue($data, ['agent_email', 'agent.email', 'user.email'])),
            'resolved_crm_user_id' => $this->resolvedCrmUserId($agentExternalId),
            'from_number' => $from['original'],
            'from_number_normalized' => $from['normalized'],
            'from_number_comparison' => $from['comparison'],
            'to_number' => $to['original'],
            'to_number_normalized' => $to['normalized'],
            'to_number_comparison' => $to['comparison'],
            'customer_number' => $customer['original'],
            'customer_number_normalized' => $customer['normalized'],
            'customer_number_comparison' => $customer['comparison'],
            'started_at' => $this->timestamp($this->firstValue($data, ['started_at', 'start_time', 'call_started_at', 'created_at', 'call_started_at']))
                ?: $this->dateAndTime($data, ['call_date', 'call_user_date'], ['call_time', 'call_user_time']),
            'answered_at' => $this->timestamp($this->firstValue($data, ['answered_at', 'answer_time', 'call_answered_at'])),
            'ended_at' => $this->timestamp($this->firstValue($data, ['ended_at', 'end_time', 'call_ended_at', 'completed_at']))
                ?: ($eventType === 'call.completed' || $eventType === 'call.missed' ? $this->dateAndTime($data, ['call_date', 'call_user_date'], ['call_time', 'call_user_time']) : null),
            'duration_seconds' => $this->duration($this->firstValue($data, ['duration_seconds', 'duration', 'call_duration.total_duration', 'call_duration.conversation_time', 'call_duration.handle_time', 'call_duration'])),
            'disposition' => $this->stringValue($this->firstValue($data, ['disposition', 'call_disposition', 'call_info.disposition'])),
            'notes' => $this->stringValue($this->firstValue($data, ['notes', 'note', 'comments', 'call_info.notes'])),
            'recording_reference' => $this->stringValue($this->firstValue($data, ['recording_id', 'recording_reference', 'recording.reference', 'recording.id'])),
            'recording_url' => $this->stringValue($this->firstValue($data, ['recording_url', 'recording_link', 'recording.url', 'recording', 'call_info.recording', 'call_info.recording_child'])),
            'recording_duration_seconds' => $this->duration($this->firstValue($data, ['recording_duration_seconds', 'recording.duration', 'recording_duration', 'call_duration.conversation_time', 'call_duration.total_duration'])),
            'raw_event_type' => $rawEventType,
            'normalized_at' => now()->toISOString(),
        ];
    }

    private function eventType(?string $rawEventType): ?string
    {
        if ($rawEventType === null) {
            return null;
        }

        $type = Str::of($rawEventType)->lower()->replace(['_', '-', ' '], '.')->squish()->toString();

        return match (true) {
            str_contains($type, 'missed') || str_contains($type, 'voicemail') => 'call.missed',
            str_contains($type, 'answered') || str_contains($type, 'answer') => 'call.answered',
            str_contains($type, 'completed') || str_contains($type, 'ended') || str_contains($type, 'end') => 'call.completed',
            str_contains($type, 'incoming') || str_contains($type, 'ringing') => 'call.ringing',
            str_contains($type, 'initiated') || str_contains($type, 'started') || str_contains($type, 'created') => 'call.initiated',
            str_contains($type, 'updated') || str_contains($type, 'update') => 'call.updated',
            default => null,
        };
    }

    private function direction(array $data, ?string $rawEventType): string
    {
        $value = $this->stringValue($this->firstValue($data, ['direction', 'call_direction', 'type', 'call_info.direction']));
        $haystack = strtolower(trim(($value ?? '').' '.($rawEventType ?? '')));

        if (str_contains($haystack, 'outbound') || str_contains($haystack, 'outgoing')) {
            return 'outbound';
        }

        if (str_contains($haystack, 'inbound') || str_contains($haystack, 'incoming')) {
            return 'inbound';
        }

        return 'unknown';
    }

    private function resolvedCrmUserId(?string $agentExternalId): ?int
    {
        if ($agentExternalId === null) {
            return null;
        }

        return JustCallUserMapping::query()
            ->where('is_active', true)
            ->where('justcall_user_id', $agentExternalId)
            ->value('user_id');
    }

    private function firstValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, 500, '');
    }

    private function phone(mixed $value): array
    {
        return $this->phoneNormalizer->normalize($value);
    }

    private function emptyPhone(): array
    {
        return ['original' => null, 'normalized' => null, 'comparison' => null];
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function duration(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && preg_match('/^(\d+):([0-5]?\d)$/', trim($value), $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        return null;
    }

    private function dateAndTime(array $data, array $dateKeys, array $timeKeys): ?string
    {
        $date = $this->stringValue($this->firstValue($data, $dateKeys));
        $time = $this->stringValue($this->firstValue($data, $timeKeys));

        if (! $date) {
            return null;
        }

        return $this->timestamp(trim($date.' '.($time ?: '00:00:00')));
    }
}
