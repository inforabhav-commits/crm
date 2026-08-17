<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowExecution;
use App\Models\WorkflowRule;
use App\Services\WorkflowRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkflowRuleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin', array $permissions = []): User
    {
        $roleModel = Role::firstOrCreate(['slug' => $role], ['name' => $role]);
        foreach ($permissions as $permission) $roleModel->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission])->id);
        $user = User::factory()->create(['password' => Hash::make('password'), 'is_active' => true]);
        $user->roles()->sync([$roleModel->id]);
        return $user;
    }

    private function value(string $type, string $name): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate(['type' => $type, 'slug' => CrmMasterValue::makeSlug($name)], ['name' => $name, 'is_active' => true]);
    }

    private function lead(User $owner, string $name = 'Workflow Lead'): Lead
    {
        return Lead::create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'phone' => '155500'.random_int(10, 99), 'lead_status_id' => $this->value('lead_status', 'New')->id, 'lead_source_id' => $this->value('lead_source', 'Web')->id, 'owner_id' => $owner->id, 'created_by_id' => $owner->id, 'updated_by_id' => $owner->id]);
    }

    private function activityType(): CrmMasterValue
    {
        return $this->value('activity_type', 'Follow-up');
    }

    public function test_rule_creation_and_authorization(): void
    {
        $admin = $this->user('admin', ['workflows.view', 'workflows.manage']);
        $this->actingAs($admin)->get('/admin/workflows')->assertOk()->assertSee('Workflow Rules');
        $this->actingAs($admin)->post('/admin/workflows', ['name' => 'New lead follow-up', 'entity_type' => Lead::class, 'trigger' => 'record_created', 'conditions_json' => '{}', 'action_type' => 'create_activity', 'action_json' => json_encode(['activity_type' => 'follow-up']), 'priority' => 10, 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('workflow_rules', ['name' => 'New lead follow-up', 'trigger' => 'record_created']);
        $agent = $this->user('agent');
        $this->actingAs($agent)->get('/admin/workflows')->assertForbidden();
    }

    public function test_lead_status_trigger_creates_follow_up_and_duplicate_event_is_skipped(): void
    {
        $owner = $this->user();
        $lead = $this->lead($owner);
        $this->activityType();
        $qualified = $this->value('lead_status', 'Qualified');
        $rule = WorkflowRule::create(['name' => 'Qualified follow-up', 'entity_type' => Lead::class, 'trigger' => 'lead_status_changed', 'conditions' => ['lead_status' => 'qualified'], 'action' => ['type' => 'create_activity', 'activity_type' => 'follow-up', 'subject' => 'Qualify follow-up', 'assigned_user_id' => $owner->id], 'is_active' => true, 'priority' => 1, 'created_by_id' => $owner->id, 'updated_by_id' => $owner->id]);
        $lead->forceFill(['lead_status_id' => $qualified->id])->save();
        $service = app(WorkflowRuleService::class);
        $context = ['previous_status' => 'new', 'event_key' => 'lead-status-event-1'];
        $service->dispatch($lead->refresh(), 'lead_status_changed', $context, $owner);
        $service->dispatch($lead->refresh(), 'lead_status_changed', $context, $owner);
        $this->assertSame(1, Activity::where('subject', 'Qualify follow-up')->count());
        $this->assertSame(1, WorkflowExecution::where('workflow_rule_id', $rule->id)->where('status', 'succeeded')->count());
    }

    public function test_opportunity_stage_trigger_sends_notification(): void
    {
        $owner = $this->user();
        $lead = $this->lead($owner, 'Opportunity Source');
        $customer = Customer::create(['name' => 'Workflow Customer', 'phone' => '15550111', 'owner_id' => $owner->id, 'is_active' => true]);
        $stage = $this->value('opportunity_stage', 'Qualification');
        $rule = WorkflowRule::create(['name' => 'Stage notification', 'entity_type' => Opportunity::class, 'trigger' => 'opportunity_stage_changed', 'conditions' => ['opportunity_stage' => 'qualification'], 'action' => ['type' => 'notify', 'recipient_user_id' => $owner->id, 'title' => 'Stage changed', 'message' => 'Opportunity moved'], 'is_active' => true, 'priority' => 1, 'created_by_id' => $owner->id, 'updated_by_id' => $owner->id]);
        $opportunity = Opportunity::create(['name' => 'Workflow Deal', 'customer_id' => $customer->id, 'source_lead_id' => $lead->id, 'stage_id' => $stage->id, 'owner_id' => $owner->id, 'amount' => 100, 'status' => 'open', 'created_by_id' => $owner->id, 'updated_by_id' => $owner->id]);
        app(WorkflowRuleService::class)->dispatch($opportunity, 'opportunity_stage_changed', ['event_key' => 'stage-event-1'], $owner);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);
        $this->assertDatabaseHas('workflow_executions', ['workflow_rule_id' => $rule->id, 'status' => 'succeeded']);
    }

    public function test_inactive_invalid_and_recursive_rules_fail_safely(): void
    {
        $owner = $this->user();
        $lead = $this->lead($owner, 'Safe Workflow Lead');
        $inactive = WorkflowRule::create(['name' => 'Inactive', 'entity_type' => Lead::class, 'trigger' => 'record_created', 'conditions' => [], 'action' => ['type' => 'notify', 'recipient_user_id' => $owner->id], 'is_active' => false, 'priority' => 1, 'created_by_id' => $owner->id, 'updated_by_id' => $owner->id]);
        $invalid = WorkflowRule::create(['name' => 'Invalid action', 'entity_type' => Lead::class, 'trigger' => 'record_updated', 'conditions' => [], 'action' => ['type' => 'unsupported'], 'is_active' => true, 'priority' => 1, 'created_by_id' => $owner->id, 'updated_by_id' => $owner->id]);
        $recursive = WorkflowRule::create(['name' => 'Recursive update', 'entity_type' => Lead::class, 'trigger' => 'record_updated', 'conditions' => [], 'action' => ['type' => 'update_field', 'field' => 'priority', 'value' => 'high'], 'is_active' => true, 'priority' => 2, 'created_by_id' => $owner->id, 'updated_by_id' => $owner->id]);
        app(WorkflowRuleService::class)->dispatch($lead, 'record_created', ['event_key' => 'inactive-event'], $owner);
        app(WorkflowRuleService::class)->dispatch($lead, 'record_updated', ['event_key' => 'invalid-event'], $owner);
        $this->assertDatabaseHas('workflow_executions', ['workflow_rule_id' => $invalid->id, 'status' => 'failed']);
        $this->assertDatabaseMissing('workflow_executions', ['workflow_rule_id' => $inactive->id]);
        $this->assertDatabaseCount('workflow_executions', 2);
        $this->assertSame('high', $lead->fresh()->priority);
        $this->assertNotNull($recursive);
    }
}
