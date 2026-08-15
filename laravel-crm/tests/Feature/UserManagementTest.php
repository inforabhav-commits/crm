<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_super_admin_can_access_users()
    {
        $superAdmin = $this->userWithRole('super-admin');

        $this->actingAs($superAdmin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('User Management');
    }

    public function test_agent_cannot_access_users()
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_user_can_be_created_with_roles()
    {
        $admin = $this->userWithRole('super-admin');
        $agentRole = $this->role('agent');

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'New Agent',
                'email' => 'agent@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'is_active' => '1',
                'roles' => [$agentRole->id],
            ])
            ->assertRedirect('/admin/users');

        $user = User::where('email', 'agent@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertTrue($user->hasRole('agent'));
    }

    public function test_user_can_be_edited_and_roles_are_updated()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent');
        $managerRole = $this->role('manager');

        $this->actingAs($admin)
            ->put("/admin/users/{$agent->id}", [
                'name' => 'Updated User',
                'email' => 'updated@example.com',
                'password' => '',
                'password_confirmation' => '',
                'is_active' => '1',
                'roles' => [$managerRole->id],
            ])
            ->assertRedirect('/admin/users');

        $agent->refresh();
        $this->assertSame('Updated User', $agent->name);
        $this->assertTrue($agent->hasRole('manager'));
        $this->assertFalse($agent->hasRole('agent'));
    }

    public function test_user_activation_and_deactivation_works()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent');

        $this->actingAs($admin)
            ->patch("/admin/users/{$agent->id}/status")
            ->assertRedirect();

        $this->assertFalse($agent->fresh()->is_active);

        $this->actingAs($admin)
            ->patch("/admin/users/{$agent->id}/status")
            ->assertRedirect();

        $this->assertTrue($agent->fresh()->is_active);
    }

    public function test_disabled_user_cannot_log_in()
    {
        User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => Hash::make('password'),
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => 'disabled@example.com',
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_validation_errors_are_handled()
    {
        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
                'roles' => [],
            ])
            ->assertSessionHasErrors(['name', 'email', 'password', 'roles']);
    }

    public function test_admin_cannot_escalate_their_own_roles()
    {
        $adminRole = $this->role('admin');
        foreach (['users.view', 'users.edit'] as $permission) {
            $adminRole->permissions()->syncWithoutDetaching($this->permission($permission)->id);
        }

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->roles()->sync([$adminRole->id]);
        $superAdminRole = $this->role('super-admin');

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}", [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => '',
                'password_confirmation' => '',
                'is_active' => '1',
                'roles' => [$superAdminRole->id],
            ])
            ->assertSessionHasErrors('roles');

        $this->assertFalse($admin->fresh()->hasRole('super-admin'));
    }
}
