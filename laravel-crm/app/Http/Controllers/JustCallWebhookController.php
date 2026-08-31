<?php

namespace App\Http\Controllers;

use App\Models\WebhookInboxEntry;
use App\Services\Integrations\JustCall\JustCallWebhookInboxProcessor;
use App\Services\Integrations\JustCall\JustCallWebhookVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JustCallWebhookController extends Controller
{
    public function __invoke(Request $request, JustCallWebhookVerifier $verifier, JustCallWebhookInboxProcessor $processor)
    {
        $raw = $request->getContent();
        if (strlen($raw) > (int) config('justcall.max_webhook_payload_bytes', 262144)) {
            return response()->json(['message' => 'Payload too large.'], 413);
        }

        if (! str_contains((string) $request->headers->get('content-type', ''), 'json')) {
            return response()->json(['message' => 'JSON payload required.'], 415);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['message' => 'Malformed JSON payload.'], 400);
        }

        if ($this->isValidationPayload($payload)) {
            return response()->json($this->validationResponse($payload));
        }

        $verification = $verifier->verify($request, $payload);
        if (! $verification['ok']) {
            Log::warning('Rejected JustCall webhook.', ['reason' => $verification['reason'] ?? 'unknown']);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $eventType = $this->eventType($payload);
        if (! $eventType) {
            return response()->json(['message' => 'Missing webhook event type.'], 422);
        }

        $externalId = $this->externalId($payload);
        $payloadHash = hash('sha256', $externalId ? 'justcall|'.$eventType.'|'.$externalId : $raw);

        $entry = WebhookInboxEntry::firstOrCreate([
            'provider' => 'justcall',
            'payload_hash' => $payloadHash,
        ], [
            'event_type' => $eventType,
            'external_id' => $externalId,
            'payload' => $this->safePayload($payload),
            'received_at' => now(),
            'processing_status' => 'pending',
            'attempt_count' => 0,
        ]);

        if ($entry->wasRecentlyCreated) {
            $processor->process($entry);
        }

        return response()->json([
            'received' => true,
            'duplicate' => ! $entry->wasRecentlyCreated,
        ]);
    }

    private function isValidationPayload(array $payload): bool
    {
        return isset($payload['challenge']) || ($payload['type'] ?? null) === 'webhook.validation';
    }

    private function validationResponse(array $payload): array
    {
        if (isset($payload['challenge'])) {
            return ['challenge' => $payload['challenge']];
        }

        return ['received' => true];
    }

    private function eventType(array $payload): ?string
    {
        return filled($payload['type'] ?? null) ? (string) $payload['type'] : null;
    }

    private function externalId(array $payload): ?string
    {
        foreach ([
            'request_id',
            'event_id',
            'id',
            'data.id',
            'data.call_id',
            'data.call_sid',
            'data.call_uuid',
            'data.message_id',
        ] as $key) {
            $value = str_contains($key, '.') ? Arr::get($payload, $key) : ($payload[$key] ?? null);
            if (filled($value)) {
                return Str::limit((string) $value, 255, '');
            }
        }

        return null;
    }

    private function safePayload(array $payload): array
    {
        return collect($payload)
            ->reject(function ($value, $key) {
                $key = strtolower((string) $key);

                return str_contains($key, 'secret')
                    || str_contains($key, 'signature')
                    || str_contains($key, 'token')
                    || str_contains($key, 'api_key')
                    || str_contains($key, 'authorization')
                    || str_contains($key, 'password');
            })
            ->map(fn ($value) => is_array($value) ? $this->safePayload($value) : $value)
            ->all();
    }
}
