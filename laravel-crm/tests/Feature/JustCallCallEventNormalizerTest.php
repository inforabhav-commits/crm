<?php

namespace Tests\Feature;

use App\Models\JustCallUserMapping;
use App\Models\User;
use App\Models\WebhookInboxEntry;
use App\Services\Integrations\JustCall\JustCallCallEventNormalizer;
use App\Services\Integrations\JustCall\JustCallWebhookInboxProcessor;
use App\Services\PhoneNumberNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JustCallCallEventNormalizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-16 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function entry(array $payload): WebhookInboxEntry
    {
        return WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => $payload['type'] ?? null,
            'external_id' => $payload['request_id'] ?? null,
            'payload_hash' => hash('sha256', json_encode($payload)),
            'payload' => $payload,
            'received_at' => now(),
            'processing_status' => 'pending',
        ]);
    }

    private function normalize(array $payload): ?array
    {
        return app(JustCallCallEventNormalizer::class)->normalize($this->entry($payload));
    }

    public function test_outbound_call_event_normalization()
    {
        $normalized = $this->normalize([
            'request_id' => 'evt-outbound',
            'type' => 'call.initiated',
            'data' => [
                'call_id' => 'call-out-1',
                'direction' => 'outbound',
                'status' => 'initiated',
                'agent_id' => 'jc-agent-1',
                'agent_name' => 'Agent One',
                'agent_email' => 'agent@example.com',
                'from_number' => '+1 (555) 010-1000',
                'to_number' => '555-010-2000',
                'started_at' => '2026-08-16 09:45:00',
            ],
        ]);

        $this->assertSame('justcall', $normalized['provider']);
        $this->assertSame('call.initiated', $normalized['event_type']);
        $this->assertSame('call-out-1', $normalized['external_call_id']);
        $this->assertSame('outbound', $normalized['direction']);
        $this->assertSame('555-010-2000', $normalized['customer_number']);
        $this->assertSame('5550102000', $normalized['customer_number_comparison']);
        $this->assertSame('2026-08-16T09:45:00.000000Z', $normalized['started_at']);
    }

    public function test_inbound_call_event_normalization()
    {
        $normalized = $this->normalize([
            'request_id' => 'evt-inbound',
            'type' => 'incoming.call',
            'data' => [
                'call_id' => 'call-in-1',
                'from' => '+44 20 7946 0958',
                'to' => '+1 555 010 3000',
            ],
        ]);

        $this->assertSame('call.ringing', $normalized['event_type']);
        $this->assertSame('inbound', $normalized['direction']);
        $this->assertSame('+44 20 7946 0958', $normalized['customer_number']);
        $this->assertSame('442079460958', $normalized['customer_number_comparison']);
    }

    public function test_answered_event_normalization()
    {
        $normalized = $this->normalize([
            'request_id' => 'evt-answer',
            'type' => 'call_answered',
            'data' => [
                'call_sid' => 'sid-1',
                'answer_time' => '2026-08-16T09:50:00+00:00',
            ],
        ]);

        $this->assertSame('call.answered', $normalized['event_type']);
        $this->assertSame('sid-1', $normalized['external_call_id']);
        $this->assertSame('2026-08-16T09:50:00.000000Z', $normalized['answered_at']);
    }

    public function test_completed_event_normalization()
    {
        $normalized = $this->normalize([
            'request_id' => 'evt-complete',
            'type' => 'call.completed',
            'data' => [
                'call_uuid' => 'uuid-1',
                'duration' => '03:15',
                'disposition' => 'Interested',
                'notes' => 'Asked for pricing.',
                'recording_url' => 'https://recordings.test/call.mp3',
                'ended_at' => '2026-08-16 09:55:00',
            ],
        ]);

        $this->assertSame('call.completed', $normalized['event_type']);
        $this->assertSame(195, $normalized['duration_seconds']);
        $this->assertSame('Interested', $normalized['disposition']);
        $this->assertSame('https://recordings.test/call.mp3', $normalized['recording_url']);
        $this->assertSame('2026-08-16T09:55:00.000000Z', $normalized['ended_at']);
    }

    public function test_missed_event_normalization()
    {
        $normalized = $this->normalize([
            'request_id' => 'evt-missed',
            'type' => 'call.missed',
            'data' => [
                'direction' => 'incoming',
                'status' => 'missed',
            ],
        ]);

        $this->assertSame('call.missed', $normalized['event_type']);
        $this->assertSame('inbound', $normalized['direction']);
        $this->assertSame('missed', $normalized['status']);
    }

    public function test_unknown_event_is_handled_safely()
    {
        $entry = $this->entry([
            'request_id' => 'evt-message',
            'type' => 'sms.received',
            'data' => ['id' => 'sms-1'],
        ]);

        $processed = app(JustCallWebhookInboxProcessor::class)->process($entry);

        $this->assertSame('unsupported', $processed->processing_status);
        $this->assertNull($processed->normalized_payload);
        $this->assertSame('Unsupported JustCall event type.', $processed->failure_summary);
    }

    public function test_direction_normalization()
    {
        $outbound = $this->normalize(['request_id' => 'evt-dir-out', 'type' => 'call.updated', 'data' => ['call_direction' => 'outgoing']]);
        $unknown = $this->normalize(['request_id' => 'evt-dir-unknown', 'type' => 'call.updated', 'data' => ['call_direction' => 'manual']]);

        $this->assertSame('outbound', $outbound['direction']);
        $this->assertSame('unknown', $unknown['direction']);
    }

    public function test_phone_normalization()
    {
        $normalized = app(PhoneNumberNormalizer::class)->normalize('+1 (555) 010-2000');
        $local = app(PhoneNumberNormalizer::class)->normalize('555 010 2000');

        $this->assertSame('+15550102000', $normalized['normalized']);
        $this->assertSame('15550102000', $normalized['comparison']);
        $this->assertSame('5550102000', $local['normalized']);
        $this->assertSame('5550102000', $local['comparison']);
    }

    public function test_agent_mapping_resolution()
    {
        $user = User::factory()->create(['is_active' => true]);
        JustCallUserMapping::create([
            'user_id' => $user->id,
            'justcall_user_id' => 'jc-resolved',
            'active_user_id' => $user->id,
            'active_justcall_user_id' => 'jc-resolved',
            'is_active' => true,
        ]);

        $normalized = $this->normalize([
            'request_id' => 'evt-agent',
            'type' => 'call.updated',
            'data' => ['agent_id' => 'jc-resolved'],
        ]);

        $this->assertSame($user->id, $normalized['resolved_crm_user_id']);
    }

    public function test_missing_optional_fields_produce_null_values()
    {
        $normalized = $this->normalize([
            'request_id' => 'evt-minimal',
            'type' => 'call.updated',
            'data' => [],
        ]);

        $this->assertSame('call.updated', $normalized['event_type']);
        $this->assertNull($normalized['external_call_id']);
        $this->assertNull($normalized['from_number']);
        $this->assertNull($normalized['started_at']);
        $this->assertNull($normalized['duration_seconds']);
    }

    public function test_duplicate_reprocessing_is_idempotent()
    {
        $entry = $this->entry([
            'request_id' => 'evt-repeat',
            'type' => 'call.completed',
            'data' => [
                'call_id' => 'repeat-call',
                'duration_seconds' => 30,
            ],
        ]);

        $processor = app(JustCallWebhookInboxProcessor::class);
        $first = $processor->process($entry);
        $second = $processor->process($first);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('normalized', $second->processing_status);
        $this->assertSame('call.completed', $second->normalized_event_type);
        $this->assertSame('repeat-call', $second->normalized_payload['external_call_id']);
        $this->assertDatabaseCount('webhook_inbox_entries', 1);
    }
}
