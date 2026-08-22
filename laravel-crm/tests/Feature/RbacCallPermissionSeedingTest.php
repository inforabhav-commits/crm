<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacCallPermissionSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_calls_initiate_and_calls_view_exist_after_seeding()
    {
        (new DatabaseSeeder)->run();

        $this->assertDatabaseHas('permissions', ['slug' => 'calls.view']);
        $this->assertDatabaseHas('permissions', ['slug' => 'calls.initiate']);
    }

    public function test_click_to_call_permissions_are_assigned_to_expected_roles()
    {
        (new DatabaseSeeder)->run();

        foreach (['agent', 'team-leader', 'manager', 'admin', 'super-admin'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $slugs = $role->permissions->pluck('slug');

            $this->assertTrue($slugs->contains('calls.initiate'), "Role {$roleSlug} is missing calls.initiate");
            $this->assertTrue($slugs->contains('calls.view'), "Role {$roleSlug} is missing calls.view");
        }
    }

    public function test_agent_team_leader_and_manager_do_not_get_justcall_administration_permissions()
    {
        (new DatabaseSeeder)->run();

        foreach (['agent', 'team-leader', 'manager'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $slugs = $role->permissions->pluck('slug');

            $this->assertFalse($slugs->contains('justcall.view'), "Role {$roleSlug} should not have justcall.view");
            $this->assertFalse($slugs->contains('justcall.manage'), "Role {$roleSlug} should not have justcall.manage");
            $this->assertFalse($slugs->contains('justcall.monitor'), "Role {$roleSlug} should not have justcall.monitor");
        }
    }

    public function test_admin_and_super_admin_retain_justcall_administration_permissions()
    {
        (new DatabaseSeeder)->run();

        foreach (['admin', 'super-admin'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $slugs = $role->permissions->pluck('slug');

            $this->assertTrue($slugs->contains('justcall.view'), "Role {$roleSlug} should have justcall.view");
            $this->assertTrue($slugs->contains('justcall.manage'), "Role {$roleSlug} should have justcall.manage");
            $this->assertTrue($slugs->contains('justcall.monitor'), "Role {$roleSlug} should have justcall.monitor");
        }
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate_permissions()
    {
        (new DatabaseSeeder)->run();
        (new DatabaseSeeder)->run();

        $this->assertSame(1, Permission::where('slug', 'calls.initiate')->count());
        $this->assertSame(
            1,
            Role::where('slug', 'agent')->firstOrFail()->permissions()->where('slug', 'calls.initiate')->count()
        );
    }

    public function test_agent_with_calls_initiate_can_pass_click_to_call_authorization_gate()
    {
        (new DatabaseSeeder)->run();

        $agent = \App\Models\User::factory()->create(['is_active' => true]);
        $agent->roles()->sync([Role::where('slug', 'agent')->firstOrFail()->id]);

        $this->assertTrue($agent->fresh()->can('calls.initiate'));
    }

    public function test_full_phone_permission_is_explicit_and_admin_only_by_default()
    {
        (new DatabaseSeeder)->run();

        $this->assertDatabaseHas('permissions', ['slug' => 'customers.view_full_phone']);

        foreach (['agent', 'team-leader', 'manager'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $this->assertFalse($role->permissions->pluck('slug')->contains('customers.view_full_phone'));
        }

        foreach (['admin', 'super-admin'] as $roleSlug) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $this->assertTrue($role->permissions->pluck('slug')->contains('customers.view_full_phone'));
        }
    }
}
