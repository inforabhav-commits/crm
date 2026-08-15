<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditGovernanceTest extends TestCase
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

    private function userWithRole(string $roleSlug, array $permissions = []): User
    {
        $role = $this->role($roleSlug);
        foreach ($permissions as $permission) {
            $role->permissions()->syncWithoutDetaching($this->permission($permission)->id);
        }

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function masterValue(string $type, string $name, int $sort = 1): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => $type,
            'slug' => CrmMasterValue::makeSlug($name),
        ], [
            'name' => $name,
            'sort_order' => $sort,
            'is_active' => true,
            'is_default' => $sort === 1,
        ]);
    }

    private function lead(?User $owner = null, array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Audit Lead',
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner?->id,
            'priority' => 'normal',
            'created_by_id' => $owner?->id,
            'updated_by_id' => $owner?->id,
        ], $overrides));
    }

    private function opportunityFor(User $owner, array $overrides = []): Opportunity
    {
        $customer = Customer::create([
            'name' => 'Audit Customer',
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);

        return Opportunity::create(array_merge([
            'name' => 'Audit Opportunity',
            'customer_id' => $customer->id,
            'stage_id' => $this->masterValue('opportunity_stage', 'Prospecting')->id,
            'owner_id' => $owner->id,
            'amount' => 1000,
            'currency' => 'USD',
            'probability' => 10,
            'status' => 'open',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    public function test_lead_update_generates_audit_record()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.edit']);
        $lead = $this->lead($agent, ['name' => 'Original Lead']);

        $this->actingAs($agent)
            ->put("/leads/{$lead->id}", [
                'name' => 'Updated Lead',
                'lead_status_id' => $lead->lead_status_id,
                'owner_id' => $agent->id,
                'priority' => 'high',
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $audit = AuditLog::where('action', 'lead.updated')->firstOrFail();
        $this->assertSame(Lead::class, $audit->entity_type);
        $this->assertSame('Original Lead', $audit->old_values['name']);
        $this->assertSame('Updated Lead', $audit->new_values['name']);
    }

    public function test_lead_assignment_generates_audit_record()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent');
        $lead = $this->lead();

        $this->actingAs($admin)
            ->post("/leads/{$lead->id}/assign", [
                'assignment_method' => 'manual',
                'owner_id' => $agent->id,
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lead.assigned',
            'entity_type' => Lead::class,
            'entity_id' => $lead->id,
        ]);
    }

    public function test_opportunity_stage_change_generates_audit_record()
    {
        $agent = $this->userWithRole('agent', ['opportunities.view', 'opportunities.change_stage']);
        $opportunity = $this->opportunityFor($agent);
        $proposal = $this->masterValue('opportunity_stage', 'Proposal', 2);

        $this->actingAs($agent)
            ->patch("/opportunities/{$opportunity->id}/stage", ['stage_id' => $proposal->id])
            ->assertRedirect("/opportunities/{$opportunity->id}");

        $audit = AuditLog::where('action', 'opportunity.stage_changed')->firstOrFail();
        $this->assertSame($opportunity->id, $audit->entity_id);
        $this->assertSame($proposal->id, $audit->new_values['stage_id']);
    }

    public function test_user_and_role_change_generates_audit_record_without_password()
    {
        $admin = $this->userWithRole('super-admin');
        $agentRole = $this->role('agent');
        $managerRole = $this->role('manager');
        $target = $this->userWithRole('agent');

        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}", [
                'name' => 'Audited User',
                'email' => $target->email,
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
                'is_active' => '1',
                'roles' => [$agentRole->id, $managerRole->id],
                'teams' => [],
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated', 'entity_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles_changed', 'entity_id' => $target->id]);
        $payload = AuditLog::where('action', 'user.updated')->firstOrFail()->toArray();
        $this->assertStringNotContainsString('new-secret-password', json_encode($payload));
        $this->assertStringNotContainsString('password', json_encode($payload['new_values']));
    }

    public function test_login_audit_works()
    {
        User::create([
            'name' => 'Login Audit User',
            'email' => 'audit-login@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->post('/login', [
            'email' => 'audit-login@example.com',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'description' => 'User logged in.',
        ]);
    }

    public function test_unauthorized_agent_cannot_access_audit_page()
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get('/admin/audit-logs')
            ->assertForbidden();
    }

    public function test_authorized_admin_can_filter_and_view_audit_logs()
    {
        $admin = $this->userWithRole('super-admin');
        $lead = $this->lead($admin);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'lead.updated',
            'entity_type' => Lead::class,
            'entity_id' => $lead->id,
            'description' => 'Lead updated.',
            'new_values' => ['name' => 'Visible audit'],
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'customer.updated',
            'entity_type' => Customer::class,
            'description' => 'Hidden audit.',
        ]);

        $this->actingAs($admin)
            ->get('/admin/audit-logs?action=lead.updated&entity_type='.urlencode(Lead::class))
            ->assertOk()
            ->assertSee('lead.updated')
            ->assertSee('Lead updated.')
            ->assertDontSee('Hidden audit.');
    }
}
