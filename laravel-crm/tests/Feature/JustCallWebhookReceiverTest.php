<?php

namespace Tests\Feature;

use App\Models\WebhookInboxEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class JustCallWebhookReceiverTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-api-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-16 09:00:00'));
        Config::set('justcall.enabled', true);
        Config::set('justcall.api_secret', $this->secret);
        Config::set('justcall.webhook_replay_tolerance', 300);
        Config::set('justcall.max_webhook_payload_bytes', 262144);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'request_id' => 'req-123',
            'webhook_url' => 'https://crm.test/webhooks/justcall',
            'url_id' => 'url-123',
            'type' => 'call.completed',
            'data' => [
                'call_id' => 'call-123',
                'status' => 'completed',
            ],
        ], $overrides);
    }

    private function signature(array $payload, string $timestamp): string
    {
        $signedPayload = $this->secret.'|'.urlencode($payload['webhook_url']).'|'.$payload['type'].'|'.$timestamp;

        return hash_hmac('sha256', $signedPayload, $this->secret);
    }

    private function headers(array $payload, ?string $timestamp = null, ?string $signature = null): array
    {
        $timestamp ??= now()->format('Y-m-d H:i:s');

        return [
            'x-justcall-signature' => $signature ?? $this->signature($payload, $timestamp),
            'x-justcall-signature-version' => 'v1',
            'x-justcall-request-timestamp' => $timestamp,
        ];
    }

    public function test_valid_signed_webhook_accepted()
    {
        $payload = $this->payload();

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload))
            ->assertOk()
            ->assertJson(['received' => true, 'duplicate' => false]);
    }

    public function test_invalid_signature_rejected()
    {
        $payload = $this->payload();

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload, signature: 'bad-signature'))
            ->assertUnauthorized();

        $this->assertDatabaseCount('webhook_inbox_entries', 0);
    }

    public function test_stale_replayed_request_rejected()
    {
        $payload = $this->payload();
        $timestamp = now()->subMinutes(10)->format('Y-m-d H:i:s');

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload, $timestamp))
            ->assertUnauthorized();

        $this->assertDatabaseCount('webhook_inbox_entries', 0);
    }

    public function test_webhook_registration_validation_request_succeeds()
    {
        $this->postJson('/webhooks/justcall', ['challenge' => 'abc123'])
            ->assertOk()
            ->assertJson(['challenge' => 'abc123']);

        $this->assertDatabaseCount('webhook_inbox_entries', 0);
    }

    public function test_valid_payload_persisted()
    {
        $payload = $this->payload();

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload))->assertOk();

        $entry = WebhookInboxEntry::firstOrFail();
        $this->assertSame('justcall', $entry->provider);
        $this->assertSame('call.completed', $entry->event_type);
        $this->assertSame('req-123', $entry->external_id);
        $this->assertSame('pending', $entry->processing_status);
        $this->assertSame('completed', $entry->payload['data']['status']);
    }

    public function test_duplicate_delivery_remains_idempotent()
    {
        $payload = $this->payload();
        $headers = $this->headers($payload);

        $this->postJson('/webhooks/justcall', $payload, $headers)
            ->assertOk()
            ->assertJson(['duplicate' => false]);

        $this->postJson('/webhooks/justcall', $payload, $headers)
            ->assertOk()
            ->assertJson(['duplicate' => true]);

        $this->assertDatabaseCount('webhook_inbox_entries', 1);
    }

    public function test_malformed_payload_handled_safely()
    {
        $this->call('POST', '/webhooks/justcall', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"bad":')
            ->assertBadRequest();

        $this->assertDatabaseCount('webhook_inbox_entries', 0);
    }

    public function test_secrets_and_signature_headers_are_not_stored()
    {
        $payload = $this->payload([
            'api_secret' => 'payload-secret',
            'data' => [
                'signature' => 'payload-signature',
                'call_id' => 'call-123',
            ],
        ]);

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload))->assertOk();

        $encoded = json_encode(WebhookInboxEntry::firstOrFail()->toArray());
        $this->assertStringNotContainsString($this->secret, $encoded);
        $this->assertStringNotContainsString('payload-secret', $encoded);
        $this->assertStringNotContainsString('payload-signature', $encoded);
        $this->assertStringNotContainsString('x-justcall-signature', $encoded);
    }

    public function test_endpoint_does_not_require_crm_login()
    {
        $payload = $this->payload(['request_id' => 'req-public']);

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload))
            ->assertOk();

        $this->assertDatabaseHas('webhook_inbox_entries', [
            'provider' => 'justcall',
            'external_id' => 'req-public',
        ]);
    }
}
