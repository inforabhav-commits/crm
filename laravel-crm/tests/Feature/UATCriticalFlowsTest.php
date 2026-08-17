<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Opportunity;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookInboxEntry;
use App\Models\WorkflowExecution;
use App\Models\WorkflowRule;
use App\Services\Integrations\JustCall\JustCallWebhookInboxProcessor;
use App\Services\WorkflowRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UATCriticalFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $permissions = [], array $overrides = []): User
    {
        $roleModel = Role::firstOrCreate(['slug' => $role], ['name' => $role]);
        foreach ($permissions as $permission) {
            $roleModel->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission])->id);
        }
        $user = User::factory()->create(array_merge(['password' => Hash::make('password'), 'is_active' => true], $overrides));
        $user->roles()->sync([$roleModel->id]);
        return $user;
    }

    private function value(string $type, string $name): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate(['type' => $type, 'slug' => CrmMasterValue::makeSlug($name)], ['name' => $name, 'is_active' => true, 'sort_order' => 1]);
    }

    private function agent(): User
    {
        return $this->user('agent', [
            'leads.view', 'leads.create', 'leads.edit', 'leads.assign', 'leads.qualify', 'leads.convert',
            'activities.view', 'activities.create', 'activities.edit', 'activities.complete',
            'customers.view', 'customers.create', 'contacts.view', 'contacts.create',
            'opportunities.view', 'opportunities.create', 'opportunities.edit', 'opportunities.change_stage',
            'calls.view', 'calls.recordings.view', 'reports.view', 'export.crm', 'import.leads',
        ]);
    }

    private function lead(User $owner, string $name = 'UAT Lead'): Lead
    {
        return Lead::create([
            'name' => $name,
            'company' => 'UAT Company',
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'phone' => '+155500'.random_int(10, 99),
            'lead_status_id' => $this->value('lead_status', 'New')->id,
            'lead_source_id' => $this->value('lead_source', 'Website')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ]);
    }

    private function qualifyPayload(): array
    {
        return [
            'qualification_status' => 'qualified',
            'qualification_need' => 'UAT CRM need',
            'qualification_budget' => '1000',
            'qualification_authority' => 'Decision maker',
            'qualification_timeline' => 'This quarter',
            'qualification_interest_level' => 'high',
        ];
    }

    public function test_uat_auth_rbac_lead_lifecycle_conversion_and_daily_workflow(): void
    {
        $admin = $this->user('super-admin');
        $agent = $this->agent();
        $other = $this->agent();
        $this->value('lead_status', 'Converted');
        $this->value('opportunity_stage', 'Prospecting');
        $this->value('activity_type', 'Call');

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $agent->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->get('/admin/workflows')->assertForbidden();

        $lead = $this->lead($agent);
        $this->actingAs($admin)->post(route('leads.assign', $lead), ['assignment_method' => 'manual', 'owner_id' => $agent->id])->assertRedirect();
        $this->actingAs($agent)->patch(route('leads.qualify', $lead), $this->qualifyPayload())->assertRedirect();
        $activity = Activity::create(['activity_type_id' => $this->value('activity_type', 'Call')->id, 'subject' => 'UAT Follow-up', 'related_type' => Lead::class, 'related_id' => $lead->id, 'assigned_user_id' => $agent->id, 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id, 'priority' => 'normal', 'status' => 'pending', 'due_at' => now()->subDay()]);
        $this->actingAs($agent)->get('/dashboard')->assertOk()->assertSee('UAT Follow-up')->assertSee('Overdue Activities');

        $this->actingAs($agent)->post(route('leads.convert', $lead), [
            'customer_name' => 'UAT Customer', 'customer_company' => 'UAT Company', 'customer_email' => $lead->email, 'customer_phone' => $lead->phone,
            'contact_first_name' => 'UAT', 'contact_last_name' => 'Contact', 'contact_email' => $lead->email, 'contact_phone' => $lead->phone,
            'opportunity_name' => 'UAT Opportunity', 'opportunity_amount' => 1000, 'expected_close_date' => '2026-09-30',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertNotNull($lead->converted_customer_id);
        $this->assertNotNull($lead->converted_contact_id);
        $this->assertNotNull($lead->converted_opportunity_id);
        $this->assertSame($lead->id, Customer::find($lead->converted_customer_id)->converted_from_lead_id);
        $this->assertSame($lead->id, Contact::find($lead->converted_contact_id)->source_lead_id);
        $this->assertSame($lead->id, $lead->convertedOpportunity->source_lead_id);
        $this->actingAs($agent)->get(route('leads.show', $lead))->assertOk()->assertSee('UAT Follow-up');
        $this->actingAs($agent)->get(route('leads.show', $this->lead($other, 'Private Lead')))->assertForbidden();
        $this->assertNotNull($activity->fresh());
    }

    public function test_uat_opportunity_lifecycle_stage_won_and_lost(): void
    {
        $agent = $this->agent();
        $customer = Customer::create(['name' => 'UAT Account', 'phone' => '+15550101', 'owner_id' => $agent->id, 'is_active' => true, 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id]);
        $prospecting = $this->value('opportunity_stage', 'Prospecting');
        $won = $this->value('opportunity_stage', 'Won');
        $lost = $this->value('opportunity_stage', 'Lost');
        $lossReason = $this->value('loss_reason', 'Budget');

        $this->actingAs($agent)->post('/opportunities', ['name' => 'UAT Deal', 'customer_id' => $customer->id, 'stage_id' => $prospecting->id, 'owner_id' => $agent->id, 'amount' => 500, 'currency' => 'USD', 'probability' => 20, 'expected_close_date' => '2026-09-30'])->assertRedirect();
        $opportunity = Opportunity::where('name', 'UAT Deal')->firstOrFail();
        $this->actingAs($agent)->put(route('opportunities.update', $opportunity), ['name' => 'UAT Updated Deal', 'customer_id' => $customer->id, 'stage_id' => $prospecting->id, 'owner_id' => $agent->id, 'amount' => 750, 'currency' => 'USD', 'probability' => 30])->assertRedirect();
        $this->actingAs($agent)->patch(route('opportunities.change-stage', $opportunity), ['stage_id' => $won->id])->assertRedirect();
        $this->assertSame('won', $opportunity->refresh()->status);

        $lostOpportunity = Opportunity::create(['name' => 'UAT Lost Deal', 'customer_id' => $customer->id, 'stage_id' => $prospecting->id, 'owner_id' => $agent->id, 'amount' => 300, 'currency' => 'USD', 'probability' => 10, 'status' => 'open', 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id]);
        $this->actingAs($agent)->patch(route('opportunities.change-stage', $lostOpportunity), ['stage_id' => $lost->id, 'loss_reason_id' => $lossReason->id])->assertRedirect();
        $this->assertSame('lost', $lostOpportunity->refresh()->status);
        $this->assertSame($lossReason->id, $lostOpportunity->loss_reason_id);
        $this->actingAs($agent)->get('/opportunities')->assertOk()->assertSee('UAT Updated Deal');
    }

    public function test_uat_justcall_lifecycle_is_mocked_and_authorized(): void
    {
        Config::set('justcall.enabled', true);
        Config::set('justcall.api_secret', 'uat-only-secret');
        Config::set('justcall.webhook_secret', 'uat-webhook-secret');
        $agent = $this->agent();
        $admin = $this->user('super-admin');
        $lead = $this->lead($agent, 'UAT Caller');
        $mapping = \App\Models\JustCallUserMapping::create(['user_id' => $agent->id, 'justcall_user_id' => 'uat-agent', 'active_user_id' => $agent->id, 'active_justcall_user_id' => 'uat-agent', 'is_active' => true]);
        $payload = ['request_id' => 'uat-webhook-1', 'webhook_url' => 'https://crm.test/webhooks/justcall', 'type' => 'call.missed', 'data' => ['call_id' => 'uat-call-1', 'direction' => 'incoming', 'agent_id' => 'uat-agent', 'from_number' => $lead->phone, 'status' => 'missed']];
        $timestamp = now()->format('Y-m-d H:i:s');
        $signature = hash_hmac('sha256', 'uat-only-secret|'.urlencode($payload['webhook_url']).'|'.$payload['type'].'|'.$timestamp, 'uat-only-secret');

        $this->postJson('/webhooks/justcall', $payload, ['x-justcall-signature' => $signature, 'x-justcall-signature-version' => 'v1', 'x-justcall-request-timestamp' => $timestamp])->assertOk();
        $entry = WebhookInboxEntry::firstOrFail();
        app(JustCallWebhookInboxProcessor::class)->process($entry);
        $call = CallLog::where('external_call_id', 'uat-call-1')->firstOrFail();
        $this->assertSame($agent->id, $call->user_id);
        $this->assertSame($lead->id, $call->lead_id);
        $this->assertSame('missed', $call->status);
        $this->assertDatabaseHas('activities', ['related_type' => Lead::class, 'related_id' => $lead->id, 'assigned_user_id' => $agent->id, 'status' => 'pending']);
        $unauthorized = $this->agent();
        $this->actingAs($unauthorized)->get(route('calls.recording', $call))->assertForbidden();
        $this->actingAs($admin)->get('/admin/justcall-settings/monitoring')->assertOk();
        $this->actingAs($admin)->get('/admin/justcall-settings/monitoring/reconcile')->assertOk()->assertSee('uat-call-1');
        $this->actingAs($admin)->post('/admin/justcall-settings/monitoring/reconcile', ['call_log_id' => $call->id, 'review_status' => 'reviewed'])->assertRedirect();
        $this->assertDatabaseHas('call_logs', ['id' => $call->id, 'review_status' => 'reviewed']);
        $this->assertNotNull($mapping->fresh());
    }

    public function test_uat_import_export_workflow_and_security_isolation(): void
    {
        $agent = $this->agent();
        $other = $this->agent();
        $status = $this->value('lead_status', 'New');
        $source = $this->value('lead_source', 'Website');
        $rule = WorkflowRule::create(['name' => 'UAT notify once', 'entity_type' => Lead::class, 'trigger' => 'record_created', 'conditions' => ['lead_source' => 'website'], 'action' => ['type' => 'notify', 'recipient_user_id' => $agent->id, 'title' => 'UAT Workflow', 'message' => 'UAT event'], 'is_active' => true, 'priority' => 1, 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id]);
        $csv = UploadedFile::fake()->createWithContent('uat.csv', "name,email,phone,status,source\nUAT Imported,uat@example.com,15550222,New,Website\n");
        $this->actingAs($agent)->post('/import-export', ['resource' => 'leads', 'file' => $csv])->assertRedirect();
        $imported = Lead::where('email', 'uat@example.com')->firstOrFail();
        app(WorkflowRuleService::class)->dispatch($imported, 'record_created', ['event_key' => 'uat-import-event'], $agent);
        app(WorkflowRuleService::class)->dispatch($imported, 'record_created', ['event_key' => 'uat-import-event'], $agent);
        $this->assertDatabaseHas('workflow_executions', ['workflow_rule_id' => $rule->id, 'status' => 'succeeded']);
        $this->assertSame(1, WorkflowExecution::where('workflow_rule_id', $rule->id)->count());
        $this->assertStringContainsString('UAT Imported', $this->actingAs($agent)->get('/export/leads')->streamedContent());
        $private = $this->lead($other, 'UAT Private');
        $this->actingAs($agent)->get('/leads/'.$private->id)->assertForbidden();
        $this->actingAs($agent)->get('/export/leads')->assertDontSee($private->name);
        $this->assertNotNull($status->fresh());
        $this->assertNotNull($source->fresh());
    }
}
