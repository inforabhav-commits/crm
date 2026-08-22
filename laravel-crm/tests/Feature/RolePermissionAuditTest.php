<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CrmMasterValue;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_role_uses_sales_executive_display_label_without_duplicate_sales_roles(): void
    {
        (new DatabaseSeeder)->run();

        $agentRole = Role::where('slug', 'agent')->firstOrFail();

        $this->assertSame('Agent', $agentRole->name);
        $this->assertSame('Sales Executive', $agentRole->display_name);

        foreach (['sales-person', 'sales-executive', 'sales-agent', 'sales-user'] as $duplicateSlug) {
            $this->assertDatabaseMissing('roles', ['slug' => $duplicateSlug]);
        }
    }

    public function test_roles_permission_ui_is_grouped_and_backend_protected(): void
    {
        (new DatabaseSeeder)->run();

        $superAdmin = $this->userWithSeededRole('super-admin');
        $agent = $this->userWithSeededRole('agent');

        $this->actingAs($superAdmin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Sales Executive')
            ->assertSee('Slug: agent')
            ->assertSee('Customers')
            ->assertSee('customers.view_full_phone')
            ->assertSee('Import / Export')
            ->assertSee('export.crm');

        $this->actingAs($agent)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }

    public function test_sales_executive_does_not_receive_admin_only_sensitive_permissions_by_default(): void
    {
        (new DatabaseSeeder)->run();

        $agentPermissions = Role::where('slug', 'agent')->firstOrFail()->permissions->pluck('slug');

        foreach ([
            'customers.view_full_phone',
            'roles.manage',
            'crm_settings.manage',
            'justcall.manage',
            'justcall.monitor',
            'ops.view',
            'audit.view',
        ] as $permission) {
            $this->assertFalse($agentPermissions->contains($permission), "Sales Executive should not have {$permission}");
        }
    }

    public function test_reporting_tree_scope_limits_sales_executive_and_expands_team_leader_visibility(): void
    {
        (new DatabaseSeeder)->run();

        $teamLeader = $this->userWithSeededRole('team-leader');
        $agent = $this->userWithSeededRole('agent', ['reports_to_id' => $teamLeader->id]);
        $otherAgent = $this->userWithSeededRole('agent');

        $ownLead = $this->leadFor($teamLeader, 'Team Leader Lead');
        $reportLead = $this->leadFor($agent, 'Report Lead');
        $hiddenLead = $this->leadFor($otherAgent, 'Hidden Lead');
        $ownCustomer = $this->customerFor($agent, 'Agent Customer');
        $hiddenCustomer = $this->customerFor($otherAgent, 'Hidden Customer');

        $this->assertTrue(Lead::whereKey($reportLead->id)->visibleTo($teamLeader)->exists());
        $this->assertTrue(Lead::whereKey($ownLead->id)->visibleTo($teamLeader)->exists());
        $this->assertFalse(Lead::whereKey($hiddenLead->id)->visibleTo($teamLeader)->exists());

        $this->assertTrue(Customer::whereKey($ownCustomer->id)->visibleTo($agent)->exists());
        $this->assertFalse(Customer::whereKey($hiddenCustomer->id)->visibleTo($agent)->exists());
    }

    private function userWithSeededRole(string $roleSlug, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge(['is_active' => true], $overrides));
        $user->roles()->sync([Role::where('slug', $roleSlug)->firstOrFail()->id]);

        return $user;
    }

    private function leadFor(User $owner, string $name): Lead
    {
        return Lead::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.uniqid().'@example.com',
            'phone' => '1555'.random_int(100000, 999999),
            'lead_status_id' => $this->masterValue('lead_status', 'New'),
            'lead_source_id' => $this->masterValue('lead_source', 'Website'),
            'owner_id' => $owner->id,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ]);
    }

    private function customerFor(User $owner, string $name): Customer
    {
        return Customer::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.uniqid().'@example.com',
            'phone' => '1555'.random_int(100000, 999999),
            'owner_id' => $owner->id,
            'is_active' => true,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ]);
    }

    private function masterValue(string $type, string $name): int
    {
        return CrmMasterValue::where('type', $type)->where('name', $name)->firstOrFail()->id;
    }
}
