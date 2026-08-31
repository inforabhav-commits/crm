<?php

namespace Tests\Feature;

use App\Models\WebhookInboxEntry;
use App\Models\CallLog;
use App\Models\CrmMasterValue;
use App\Models\JustCallUserMapping;
use App\Models\Lead;
use App\Models\User;
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

    public function test_valid_payload_persisted_and_processed()
    {
        $payload = $this->payload();

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload))->assertOk();

        $entry = WebhookInboxEntry::firstOrFail();
        $this->assertSame('justcall', $entry->provider);
        $this->assertSame('call.completed', $entry->event_type);
        $this->assertSame('req-123', $entry->external_id);
        $this->assertSame('normalized', $entry->processing_status);
        $this->assertSame('completed', $entry->payload['data']['status']);
        $this->assertSame('call.completed', $entry->normalized_event_type);
        $this->assertDatabaseHas('call_logs', [
            'provider' => 'justcall',
            'external_call_id' => 'call-123',
            'status' => 'completed',
        ]);
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
        $this->assertDatabaseCount('call_logs', 1);
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
            'Authorization' => 'Bearer payload-authorization',
            'data' => [
                'signature' => 'payload-signature',
                'call_id' => 'call-123',
            ],
        ]);

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload))->assertOk();

        $encoded = json_encode(WebhookInboxEntry::firstOrFail()->toArray());
        $this->assertStringNotContainsString($this->secret, $encoded);
        $this->assertStringNotContainsString('payload-secret', $encoded);
        $this->assertStringNotContainsString('payload-authorization', $encoded);
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

    public function test_v2_call_initiated_webhook_creates_call_log_from_call_info()
    {
        $payload = $this->v2Payload([
            'request_id' => 'req-v2-start',
            'type' => 'call.initiated',
            'data' => [
                'id' => null,
                'call_sid' => 'CA-v2-start',
                'contact_number' => '15550102000',
                'justcall_number' => '15550101000',
                'agent_id' => 'jc-agent-v2',
                'call_date' => '2026-08-16',
                'call_time' => '09:01:02',
                'call_info' => [
                    'direction' => 'Outgoing',
                    'recording' => '',
                ],
            ],
        ]);

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload))->assertOk();

        $callLog = CallLog::where('external_call_id', 'CA-v2-start')->firstOrFail();
        $this->assertSame('outbound', $callLog->direction);
        $this->assertSame('initiated', $callLog->status);
        $this->assertSame('15550102000', $callLog->customer_number_normalized);
        $this->assertSame('unavailable', $callLog->recording_status);
    }

    public function test_v2_completed_webhook_persists_duration_recording_agent_and_phone_match()
    {
        $agent = User::factory()->create(['is_active' => true]);
        JustCallUserMapping::create([
            'user_id' => $agent->id,
            'justcall_user_id' => 'jc-agent-v2',
            'active_user_id' => $agent->id,
            'active_justcall_user_id' => 'jc-agent-v2',
            'is_active' => true,
        ]);

        $leadStatus = CrmMasterValue::firstOrCreate([
            'type' => 'lead_status',
            'slug' => 'new',
        ], [
            'name' => 'New',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $lead = Lead::create([
            'name' => 'Matched Lead',
            'phone' => '+1 555 010 2000',
            'lead_status_id' => $leadStatus->id,
            'owner_id' => $agent->id,
            'priority' => 'normal',
        ]);

        $payload = $this->v2Payload([
            'request_id' => 'req-v2-complete',
            'type' => 'call.completed',
            'data' => [
                'id' => 178632123,
                'call_sid' => 'CA-v2-complete',
                'contact_number' => '15550102000',
                'justcall_number' => '15550101000',
                'agent_id' => 'jc-agent-v2',
                'agent_name' => 'Mapped Agent',
                'agent_email' => 'agent@example.test',
                'call_date' => '2026-08-16',
                'call_time' => '09:10:00',
                'call_info' => [
                    'direction' => 'Outgoing',
                    'type' => 'answered',
                    'disposition' => 'Interested',
                    'notes' => 'Asked for pricing.',
                    'recording' => 'https://callingservice.justcall.test/recording.mp3?token=secret-token',
                ],
                'call_duration' => [
                    'friendly_duration' => '00:10:33',
                    'total_duration' => 633,
                    'conversation_time' => 600,
                ],
            ],
        ]);

        $this->postJson('/webhooks/justcall', $payload, $this->headers($payload))->assertOk();

        $callLog = CallLog::where('external_call_id', 'CA-v2-complete')->firstOrFail();
        $this->assertSame('completed', $callLog->status);
        $this->assertSame('outbound', $callLog->direction);
        $this->assertSame(633, $callLog->duration_seconds);
        $this->assertSame('available', $callLog->recording_status);
        $this->assertSame('https://callingservice.justcall.test/recording.mp3?token=secret-token', $callLog->recording_url);
        $this->assertSame($agent->id, $callLog->user_id);
        $this->assertSame($lead->id, $callLog->lead_id);

        $encoded = json_encode(WebhookInboxEntry::firstOrFail()->toArray());
        $this->assertStringNotContainsString($this->secret, $encoded);
    }

    private function v2Payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'request_id' => 'req-v2',
            'webhook_url' => 'https://crm.test/webhooks/justcall',
            'url_id' => 'url-v2',
            'type' => 'call.completed',
            'metadata' => [],
            'data' => [],
        ], $overrides);
    }
}
