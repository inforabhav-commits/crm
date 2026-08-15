<?php

namespace Tests\Feature;

use App\Models\Activity;
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

class CallDispositionNotesSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-16 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function masterValue(string $type, string $name): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => $type,
            'slug' => CrmMasterValue::makeSlug($name),
        ], [
            'name' => $name,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function lead(User $owner): Lead
    {
        return Lead::create([
            'name' => 'Call Lead',
            'phone' => '+1 555 010 2000',
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
        ]);
    }

    private function callLog(User $owner): CallLog
    {
        $lead = $this->lead($owner);

        return CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'call-123',
            'external_event_id' => 'event-123',
            'direction' => 'outbound',
            'status' => 'completed',
            'user_id' => $owner->id,
            'customer_number' => '+1 555 010 2000',
            'customer_number_normalized' => '15550102000',
            'lead_id' => $lead->id,
            'last_event_at' => now(),
        ]);
    }

    private function processProviderEvent(string $callId, array $data = []): CallLog
    {
        $payload = [
            'request_id' => 'evt-'.uniqid(),
            'type' => 'call.completed',
            'data' => array_merge([
                'call_id' => $callId,
                'direction' => 'outbound',
                'to_number' => '+1 555 010 2000',
                'duration_seconds' => 60,
            ], $data),
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

        return CallLog::where('external_call_id', $callId)->firstOrFail();
    }

    public function test_authorized_call_disposition_update()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.update', 'leads.view']);
        $callLog = $this->callLog($agent);

        $this->actingAs($agent)
            ->patch(route('calls.update', $callLog), [
                'crm_disposition' => 'Connected',
                'crm_notes' => '',
            ])
            ->assertRedirect(route('calls.show', $callLog));

        $callLog->refresh();
        $this->assertSame('Connected', $callLog->crm_disposition);
        $this->assertSame('Connected', $callLog->disposition);
    }

    public function test_call_notes_update()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.update', 'leads.view']);
        $callLog = $this->callLog($agent);

        $this->actingAs($agent)
            ->patch(route('calls.update', $callLog), [
                'crm_disposition' => 'Interested',
                'crm_notes' => 'Customer asked for a quote.',
            ])
            ->assertRedirect(route('calls.show', $callLog));

        $this->assertSame('Customer asked for a quote.', $callLog->fresh()->crm_notes);
        $this->assertSame('Customer asked for a quote.', $callLog->fresh()->notes);
    }

    public function test_unauthorized_update_blocked()
    {
        $agent = $this->userWithRole('agent', ['calls.update']);
        $other = $this->userWithRole('agent');
        $callLog = $this->callLog($other);

        $this->actingAs($agent)
            ->patch(route('calls.update', $callLog), ['crm_disposition' => 'Connected'])
            ->assertForbidden();
    }

    public function test_provider_disposition_preserved()
    {
        $callLog = $this->processProviderEvent('call-provider', [
            'disposition' => 'Provider Interested',
            'notes' => 'Provider note.',
        ]);

        $this->assertSame('Provider Interested', $callLog->provider_disposition);
        $this->assertSame('Provider Interested', $callLog->disposition);
        $this->assertSame('Provider note.', $callLog->provider_notes);
    }

    public function test_manual_crm_notes_are_not_blindly_overwritten()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.update', 'leads.view']);
        $callLog = $this->processProviderEvent('call-manual', [
            'disposition' => 'Provider Connected',
            'notes' => 'Provider first note.',
        ]);
        $lead = $this->lead($agent);
        $callLog->forceFill(['lead_id' => $lead->id, 'user_id' => $agent->id])->save();

        $this->actingAs($agent)->patch(route('calls.update', $callLog), [
            'crm_disposition' => 'Callback Requested',
            'crm_notes' => 'CRM note wins.',
        ]);

        $updated = $this->processProviderEvent('call-manual', [
            'disposition' => 'Provider Later',
            'notes' => 'Provider later note.',
        ]);

        $this->assertSame('Provider Later', $updated->provider_disposition);
        $this->assertSame('Provider later note.', $updated->provider_notes);
        $this->assertSame('Callback Requested', $updated->crm_disposition);
        $this->assertSame('CRM note wins.', $updated->crm_notes);
        $this->assertSame('CRM note wins.', $updated->notes);
    }

    public function test_follow_up_activity_created_once()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.update', 'leads.view']);
        $callLog = $this->callLog($agent);
        $this->masterValue('activity_type', 'Follow-up');

        $payload = [
            'crm_disposition' => 'Callback Requested',
            'crm_notes' => 'Call again tomorrow.',
            'follow_up_required' => '1',
            'next_follow_up_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($agent)->patch(route('calls.update', $callLog), $payload)->assertRedirect();
        $this->actingAs($agent)->patch(route('calls.update', $callLog->fresh()), $payload)->assertRedirect();

        $this->assertDatabaseCount('activities', 1);
        $activity = Activity::firstOrFail();
        $this->assertSame('Call follow-up', $activity->subject);
        $this->assertSame($callLog->fresh()->follow_up_activity_id, $activity->id);
    }

    public function test_timeline_shows_updated_disposition_and_notes()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.update', 'leads.view']);
        $callLog = $this->callLog($agent);

        $this->actingAs($agent)->patch(route('calls.update', $callLog), [
            'crm_disposition' => 'Interested',
            'crm_notes' => 'Timeline note.',
        ]);

        $this->actingAs($agent)
            ->get(route('leads.show', $callLog->lead))
            ->assertOk()
            ->assertSee('Interested')
            ->assertSee('Timeline note.');
    }

    public function test_audit_record_created()
    {
        $agent = $this->userWithRole('agent', ['calls.view', 'calls.update', 'leads.view']);
        $callLog = $this->callLog($agent);

        $this->actingAs($agent)->patch(route('calls.update', $callLog), [
            'crm_disposition' => 'Voicemail',
            'crm_notes' => 'Left a message.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'call_log.updated',
            'entity_type' => CallLog::class,
            'entity_id' => $callLog->id,
            'user_id' => $agent->id,
        ]);
        $encoded = json_encode(AuditLog::where('action', 'call_log.updated')->firstOrFail()->toArray());
        $this->assertStringNotContainsString('api_secret', $encoded);
    }
}
