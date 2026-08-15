<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CallLog;
use App\Models\CrmMasterValue;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookInboxEntry;
use App\Services\Integrations\JustCall\JustCallWebhookInboxProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CallRecordingAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-16 13:00:00'));
    }

    private function userWithRole(string $roleSlug, array $permissions = []): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => str($roleSlug)->replace('-', ' ')->title()->toString()]);
        foreach ($permissions as $permission) {
            $model = Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]);
            $role->permissions()->syncWithoutDetaching($model->id);
        }

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function lead(User $owner): Lead
    {
        return Lead::create([
            'name' => 'Recording Lead',
            'phone' => '+1 555 010 2000',
            'lead_status_id' => CrmMasterValue::firstOrCreate([
                'type' => 'lead_status',
                'slug' => 'new',
            ], [
                'name' => 'New',
                'is_active' => true,
                'sort_order' => 1,
            ])->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
        ]);
    }

    private function callLog(User $owner, array $overrides = []): CallLog
    {
        $lead = $this->lead($owner);

        return CallLog::create(array_merge([
            'provider' => 'justcall',
            'external_call_id' => 'call-recording',
            'external_event_id' => 'event-recording',
            'direction' => 'outbound',
            'status' => 'completed',
            'user_id' => $owner->id,
            'customer_number' => '+1 555 010 2000',
            'customer_number_normalized' => '15550102000',
            'lead_id' => $lead->id,
            'recording_reference' => 'rec-123',
            'recording_url' => 'https://recordings.justcall.test/audio.mp3?token=temporary-secret',
            'recording_status' => 'available',
            'recording_duration_seconds' => 90,
            'recording_fetched_at' => now(),
            'last_event_at' => now(),
        ], $overrides));
    }

    public function test_authorized_user_can_access_available_recording()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.recordings.view', 'leads.view']);
        $callLog = $this->callLog($agent);

        $response = $this->actingAs($agent)
            ->get(route('calls.recording', $callLog))
            ->assertRedirect();

        $this->assertSame($callLog->recording_url, $response->headers->get('Location'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'call_recording.accessed',
            'entity_type' => CallLog::class,
            'entity_id' => $callLog->id,
            'user_id' => $agent->id,
        ]);
    }

    public function test_unauthorized_user_blocked()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'leads.view']);
        $callLog = $this->callLog($agent);

        $this->actingAs($agent)
            ->get(route('calls.recording', $callLog))
            ->assertForbidden();
    }

    public function test_call_log_visibility_still_enforced()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.recordings.view', 'leads.view']);
        $other = $this->userWithRole('agent');
        $callLog = $this->callLog($other);

        $this->actingAs($agent)
            ->get(route('calls.recording', $callLog))
            ->assertForbidden();
    }

    public function test_missing_recording_handled_safely()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.recordings.view', 'leads.view']);
        $callLog = $this->callLog($agent, [
            'recording_reference' => null,
            'recording_url' => null,
            'recording_status' => 'unavailable',
        ]);

        $this->actingAs($agent)
            ->from(route('calls.show', $callLog))
            ->get(route('calls.recording', $callLog))
            ->assertRedirect(route('calls.show', $callLog))
            ->assertSessionHas('error', 'Recording is not available yet.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'call_recording.access_failed',
            'entity_type' => CallLog::class,
            'entity_id' => $callLog->id,
        ]);
    }

    public function test_raw_recording_url_not_rendered_in_blade()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.recordings.view', 'leads.view']);
        $callLog = $this->callLog($agent);

        $this->actingAs($agent)
            ->get(route('calls.show', $callLog))
            ->assertOk()
            ->assertSee('Access Recording')
            ->assertSee(route('calls.recording', $callLog), false)
            ->assertDontSee($callLog->recording_url, false)
            ->assertDontSee('temporary-secret');
    }

    public function test_provider_url_is_not_stored_in_audit()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.recordings.view', 'leads.view']);
        $callLog = $this->callLog($agent);

        $this->actingAs($agent)->get(route('calls.recording', $callLog));

        $encoded = json_encode(AuditLog::where('action', 'call_recording.accessed')->firstOrFail()->toArray());
        $this->assertStringNotContainsString($callLog->recording_url, $encoded);
        $this->assertStringNotContainsString('temporary-secret', $encoded);
    }

    public function test_recording_metadata_from_normalized_event_is_available()
    {
        $payload = [
            'request_id' => 'evt-recording',
            'type' => 'call.completed',
            'data' => [
                'call_id' => 'call-from-webhook',
                'direction' => 'outbound',
                'to_number' => '+1 555 010 2000',
                'call_info' => [
                    'recording' => 'https://recordings.justcall.test/from-webhook.mp3?token=temp',
                ],
            ],
        ];
        $entry = WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => $payload['type'],
            'external_id' => $payload['request_id'],
            'payload_hash' => hash('sha256', json_encode($payload)),
            'payload' => $payload,
            'received_at' => now(),
            'processing_status' => 'pending',
        ]);

        app(JustCallWebhookInboxProcessor::class)->process($entry);

        $callLog = CallLog::where('external_call_id', 'call-from-webhook')->firstOrFail();
        $this->assertSame('available', $callLog->recording_status);
        $this->assertSame('https://recordings.justcall.test/from-webhook.mp3?token=temp', $callLog->recording_url);
        $this->assertNotNull($callLog->recording_fetched_at);
    }
}
