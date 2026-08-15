<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $slug): Role
    {
        return Role::firstOrCreate([
            'slug' => $slug,
        ], [
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
        ]);
    }

    private function permission(string $slug): Permission
    {
        return Permission::firstOrCreate([
            'slug' => $slug,
        ], [
            'name' => $slug,
        ]);
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

    public function test_super_admin_can_create_team()
    {
        $superAdmin = $this->userWithRole('super-admin');
        $manager = User::factory()->create(['is_active' => true]);
        $agent = User::factory()->create(['is_active' => true]);

        $this->actingAs($superAdmin)
            ->post('/admin/teams', [
                'name' => 'North Sales',
                'description' => 'North region team',
                'manager_id' => $manager->id,
                'members' => [$agent->id],
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/teams');

        $team = Team::where('name', 'North Sales')->firstOrFail();
        $this->assertSame($manager->id, $team->manager_id);
        $this->assertTrue($team->members()->whereKey($agent->id)->exists());
    }

    public function test_admin_with_permission_can_manage_team()
    {
        $admin = $this->userWithRole('admin', ['teams.view', 'teams.create', 'teams.edit', 'teams.manage_members']);
        $manager = User::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->post('/admin/teams', [
                'name' => 'Support Sales',
                'manager_id' => $manager->id,
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/teams');

        $team = Team::where('name', 'Support Sales')->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/teams/{$team->id}", [
                'name' => 'Support Revenue',
                'manager_id' => $manager->id,
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/teams');

        $this->assertSame('Support Revenue', $team->fresh()->name);
    }

    public function test_unauthorized_agent_cannot_manage_teams()
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get('/admin/teams')
            ->assertForbidden();
    }

    public function test_members_can_be_assigned_and_removed()
    {
        $superAdmin = $this->userWithRole('super-admin');
        $firstAgent = User::factory()->create(['is_active' => true]);
        $secondAgent = User::factory()->create(['is_active' => true]);
        $team = Team::create(['name' => 'Inside Sales', 'is_active' => true]);
        $team->members()->sync([$firstAgent->id]);

        $this->actingAs($superAdmin)
            ->put("/admin/teams/{$team->id}", [
                'name' => 'Inside Sales',
                'is_active' => '1',
                'members' => [$secondAgent->id],
            ])
            ->assertRedirect('/admin/teams');

        $team->refresh();
        $this->assertFalse($team->members()->whereKey($firstAgent->id)->exists());
        $this->assertTrue($team->members()->whereKey($secondAgent->id)->exists());
    }

    public function test_reporting_manager_can_be_assigned_on_user_edit()
    {
        $superAdmin = $this->userWithRole('super-admin');
        $manager = User::factory()->create(['is_active' => true]);
        $agent = $this->userWithRole('agent');
        $agentRole = $this->role('agent');

        $this->actingAs($superAdmin)
            ->put("/admin/users/{$agent->id}", [
                'name' => $agent->name,
                'email' => $agent->email,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => '1',
                'roles' => [$agentRole->id],
                'reports_to_id' => $manager->id,
            ])
            ->assertRedirect('/admin/users');

        $this->assertSame($manager->id, $agent->fresh()->reports_to_id);
    }

    public function test_self_reporting_is_rejected()
    {
        $superAdmin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent');
        $agentRole = $this->role('agent');

        $this->actingAs($superAdmin)
            ->put("/admin/users/{$agent->id}", [
                'name' => $agent->name,
                'email' => $agent->email,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => '1',
                'roles' => [$agentRole->id],
                'reports_to_id' => $agent->id,
            ])
            ->assertSessionHasErrors('reports_to_id');
    }

    public function test_circular_reporting_hierarchy_is_rejected()
    {
        $superAdmin = $this->userWithRole('super-admin');
        $manager = $this->userWithRole('manager');
        $teamLeader = $this->userWithRole('team-leader');
        $managerRole = $this->role('manager');
        $teamLeaderRole = $this->role('team-leader');

        $teamLeader->forceFill(['reports_to_id' => $manager->id])->save();

        $this->actingAs($superAdmin)
            ->put("/admin/users/{$manager->id}", [
                'name' => $manager->name,
                'email' => $manager->email,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => '1',
                'roles' => [$managerRole->id],
                'reports_to_id' => $teamLeader->id,
            ])
            ->assertSessionHasErrors('reports_to_id');

        $this->assertNull($manager->fresh()->reports_to_id);
        $this->assertTrue($teamLeader->fresh()->hasRole('team-leader'));
        $this->assertTrue($teamLeaderRole->exists);
    }

    public function test_inactive_team_behavior_works()
    {
        $superAdmin = $this->userWithRole('super-admin');
        $team = Team::create(['name' => 'Dormant Team', 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->patch("/admin/teams/{$team->id}/status")
            ->assertRedirect();

        $this->assertFalse($team->fresh()->is_active);
    }
}
