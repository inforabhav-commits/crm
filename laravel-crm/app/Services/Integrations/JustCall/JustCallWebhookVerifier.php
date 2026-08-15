<?php

namespace App\Services\Integrations\JustCall;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class JustCallWebhookVerifier
{
    public function __construct(private ?JustCallConfig $config = null)
    {
        $this->config ??= JustCallConfig::fromConfig();
    }

    public function verify(Request $request, array $payload): array
    {
        if (! $this->config->enabled()) {
            return ['ok' => false, 'reason' => 'disabled'];
        }

        if ($this->config->apiSecret() === '') {
            return ['ok' => false, 'reason' => 'missing_secret'];
        }

        $signature = (string) $request->headers->get('x-justcall-signature', '');
        $version = (string) $request->headers->get('x-justcall-signature-version', '');
        $timestamp = (string) $request->headers->get('x-justcall-request-timestamp', '');

        if ($signature === '' || $version !== 'v1' || $timestamp === '') {
            return ['ok' => false, 'reason' => 'missing_headers'];
        }

        if (! $this->timestampIsFresh($timestamp)) {
            return ['ok' => false, 'reason' => 'stale_timestamp'];
        }

        $webhookUrl = (string) ($payload['webhook_url'] ?? '');
        $type = (string) ($payload['type'] ?? '');
        if ($webhookUrl === '' || $type === '') {
            return ['ok' => false, 'reason' => 'missing_payload_fields'];
        }

        $secret = $this->config->apiSecret();
        $signedPayload = $secret.'|'.urlencode($webhookUrl).'|'.$type.'|'.$timestamp;
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        return [
            'ok' => hash_equals($expected, $signature),
            'reason' => 'signature_mismatch',
        ];
    }

    public function signatureForPayload(array $payload, string $timestamp): string
    {
        $secret = $this->config->apiSecret();
        $signedPayload = $secret.'|'.urlencode((string) ($payload['webhook_url'] ?? '')).'|'.((string) ($payload['type'] ?? '')).'|'.$timestamp;

        return hash_hmac('sha256', $signedPayload, $secret);
    }

    private function timestampIsFresh(string $timestamp): bool
    {
        try {
            $requestTime = is_numeric($timestamp)
                ? Carbon::createFromTimestamp((int) $timestamp)
                : Carbon::parse($timestamp);
        } catch (\Throwable) {
            return false;
        }

        $tolerance = max(0, (int) config('justcall.webhook_replay_tolerance', 300));
        if ($tolerance === 0) {
            return true;
        }

        return abs(now()->diffInSeconds($requestTime, false)) <= $tolerance;
    }
}
