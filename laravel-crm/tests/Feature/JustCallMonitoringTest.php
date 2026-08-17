<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CallLog;
use App\Models\CrmMasterValue;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\JustCallUserMapping;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookInboxEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JustCallMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $slug): Role
    {
        return Role::firstOrCreate(['slug' => $slug], ['name' => str($slug)->replace('-', ' ')->title()->toString()]);
    }

    private function permission(string $slug): Permission
    {
        return Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
    }

    private function userWithPermission(string $permission, array $overrides = []): User
    {
        $role = $this->role('monitor-'.str($permission)->slug()->toString());
        $role->permissions()->syncWithoutDetaching($this->permission($permission)->id);

        $user = User::factory()->create(array_merge([
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $overrides));
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function ensureLeadMasterData(): void
    {
        CrmMasterValue::firstOrCreate([
            'type' => 'lead_status',
            'slug' => 'new',
        ], [
            'name' => 'New',
            'is_active' => true,
        ]);

        CrmMasterValue::firstOrCreate([
            'type' => 'lead_source',
            'slug' => 'manual',
        ], [
            'name' => 'Manual',
            'is_active' => true,
        ]);
    }

    public function test_monitoring_page_requires_justcall_monitor_permission()
    {
        $monitorAdmin = $this->userWithPermission('justcall.monitor');
        $agent = $this->userWithPermission('calls.view');

        $this->actingAs($monitorAdmin)
            ->get('/admin/justcall-settings/monitoring')
            ->assertOk();

        $this->actingAs($agent)
            ->get('/admin/justcall-settings/monitoring')
            ->assertForbidden();
    }

    public function test_summary_counts_and_unmatched_calls_are_visible()
    {
        $admin = $this->userWithPermission('justcall.monitor');
        $this->ensureLeadMasterData();

        $lead = Lead::create([
            'name' => 'Summary Lead',
            'phone' => '+15550022',
            'owner_id' => $admin->id,
            'lead_status_id' => CrmMasterValue::where('type', 'lead_status')->where('slug', 'new')->value('id'),
            'lead_source_id' => CrmMasterValue::where('type', 'lead_source')->where('slug', 'manual')->value('id'),
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);

        WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => 'call.missed',
            'external_id' => 'evt-1',
            'payload_hash' => hash('sha256', 'evt-1'),
            'payload' => ['type' => 'call.missed'],
            'received_at' => now(),
            'processing_status' => 'pending',
            'attempt_count' => 0,
        ]);

        WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => 'call.completed',
            'external_id' => 'evt-2',
            'payload_hash' => hash('sha256', 'evt-2'),
            'payload' => ['type' => 'call.completed'],
            'received_at' => now(),
            'processing_status' => 'failed',
            'failure_summary' => 'timeout',
            'attempt_count' => 2,
        ]);

        CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'call-1',
            'direction' => 'inbound',
            'status' => 'missed',
            'customer_number' => '+15550011',
            'customer_number_normalized' => '15550011',
            'last_event_at' => now(),
        ]);

        CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'call-2',
            'direction' => 'outbound',
            'status' => 'completed',
            'customer_number' => '+15550022',
            'customer_number_normalized' => '15550022',
            'lead_id' => $lead->id,
            'last_event_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/justcall-settings/monitoring')
            ->assertOk()
            ->assertSee('Webhook inbox')
            ->assertSee('Unmatched calls')
            ->assertSee('2');
    }

    public function test_ambiguous_call_appears_on_monitoring_and_reconciliation_view()
    {
        $admin = $this->userWithPermission('justcall.monitor');

        $customer = Customer::create(['name' => 'Customer A', 'phone' => '+15550050', 'owner_id' => $admin->id, 'is_active' => true]);
        $customerTwo = Customer::create(['name' => 'Customer B', 'phone' => '+15550050', 'owner_id' => $admin->id, 'is_active' => true]);

        CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'call-ambiguous',
            'direction' => 'inbound',
            'status' => 'missed',
            'customer_number' => '+15550050',
            'customer_number_normalized' => '15550050',
            'last_event_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/justcall-settings/monitoring')
            ->assertOk()
            ->assertSee('Ambiguous')
            ->assertSee('call-ambiguous');

        $this->actingAs($admin)
            ->get('/admin/justcall-settings/monitoring/reconcile')
            ->assertOk()
            ->assertSee('call-ambiguous');
    }

    public function test_manual_reconciliation_links_call_safely()
    {
        $admin = $this->userWithPermission('justcall.manage');
        $this->ensureLeadMasterData();
        $lead = Lead::create([
            'name' => 'New Lead',
            'phone' => '+15550099',
            'owner_id' => $admin->id,
            'lead_status_id' => 1,
            'lead_source_id' => 1,
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);

        $callLog = CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'call-link',
            'direction' => 'inbound',
            'status' => 'missed',
            'customer_number' => '+15550099',
            'customer_number_normalized' => '15550099',
            'last_event_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/justcall-settings/monitoring/reconcile', [
                'call_log_id' => $callLog->id,
                'related_type' => Lead::class,
                'related_id' => $lead->id,
                'assign_agent_id' => $admin->id,
                'review_status' => 'resolved',
            ])
            ->assertRedirect();

        $callLog->refresh();
        $this->assertSame($lead->id, $callLog->lead_id);
        $this->assertSame($admin->id, $callLog->user_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'justcall.call_reconciled',
            'entity_type' => CallLog::class,
            'entity_id' => $callLog->id,
        ]);
    }

    public function test_webhook_reprocessing_remains_idempotent()
    {
        $admin = $this->userWithPermission('justcall.manage');
        $entry = WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => 'call.missed',
            'external_id' => 'evt-reprocess',
            'payload_hash' => hash('sha256', 'evt-reprocess'),
            'payload' => ['type' => 'call.missed', 'data' => ['call_id' => 'call-reprocess', 'status' => 'missed']],
            'received_at' => now(),
            'processing_status' => 'failed',
            'failure_summary' => 'temporary',
            'attempt_count' => 1,
        ]);

        $this->actingAs($admin)
            ->post('/admin/justcall-settings/monitoring/reprocess', ['entry_id' => $entry->id])
            ->assertRedirect();

        $entry->refresh();
        $this->assertGreaterThan(1, $entry->attempt_count);
        $this->assertDatabaseCount('call_logs', 1);
    }

    public function test_failed_reprocessing_records_failure_safely()
    {
        $admin = $this->userWithPermission('justcall.manage');
        $entry = WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => 'call.unsupported',
            'external_id' => 'evt-unsupported',
            'payload_hash' => hash('sha256', 'evt-unsupported'),
            'payload' => ['type' => 'call.unsupported', 'api_secret' => 'top-secret'],
            'received_at' => now(),
            'processing_status' => 'pending',
            'attempt_count' => 0,
        ]);

        $this->actingAs($admin)
            ->post('/admin/justcall-settings/monitoring/reprocess', ['entry_id' => $entry->id])
            ->assertRedirect();

        $entry->refresh();
        $this->assertStringNotContainsString('top-secret', json_encode($entry->toArray()));
        $this->assertNotNull($entry->failure_summary);
    }

    public function test_unmapped_agent_issue_appears()
    {
        $admin = $this->userWithPermission('justcall.monitor');
        CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'call-unmapped',
            'direction' => 'inbound',
            'status' => 'missed',
            'agent_external_id' => 'missing-agent',
            'customer_number' => '+15550070',
            'customer_number_normalized' => '15550070',
            'last_event_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/justcall-settings/monitoring')
            ->assertOk()
            ->assertSee('Unmapped JustCall agents');
    }

    public function test_normal_agent_cannot_access_monitoring()
    {
        $user = $this->userWithPermission('calls.view');

        $this->actingAs($user)
            ->get('/admin/justcall-settings/monitoring')
            ->assertForbidden();
    }

    public function test_audit_record_created_for_reconciliation_actions()
    {
        $admin = $this->userWithPermission('justcall.manage');
        $this->ensureLeadMasterData();
        $lead = Lead::create([
            'name' => 'Audit Lead',
            'phone' => '+15550088',
            'owner_id' => $admin->id,
            'lead_status_id' => 1,
            'lead_source_id' => 1,
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);
        $callLog = CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'call-audit',
            'direction' => 'inbound',
            'status' => 'missed',
            'customer_number' => '+15550088',
            'customer_number_normalized' => '15550088',
            'last_event_at' => now(),
        ]);

        $this->actingAs($admin)->post('/admin/justcall-settings/monitoring/reconcile', [
            'call_log_id' => $callLog->id,
            'related_type' => Lead::class,
            'related_id' => $lead->id,
            'review_status' => 'resolved',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'justcall.call_reconciled',
            'entity_type' => CallLog::class,
        ]);
    }

    public function test_secrets_and_raw_auth_data_are_not_rendered_in_monitoring_views()
    {
        $admin = $this->userWithPermission('justcall.monitor');

        WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => 'call.completed',
            'external_id' => 'evt-secret',
            'payload_hash' => hash('sha256', 'evt-secret'),
            'payload' => ['type' => 'call.completed', 'api_secret' => 'super-secret', 'auth' => ['Authorization' => 'Bearer abc']],
            'received_at' => now(),
            'processing_status' => 'pending',
            'attempt_count' => 0,
        ]);

        $this->actingAs($admin)
            ->get('/admin/justcall-settings/monitoring')
            ->assertOk()
            ->assertDontSee('super-secret')
            ->assertDontSee('Authorization');
    }
}
