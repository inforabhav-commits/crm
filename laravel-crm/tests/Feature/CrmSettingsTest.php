<?php

namespace Tests\Feature;

use App\Models\CrmMasterValue;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrmSettingsTest extends TestCase
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

    public function test_super_admin_can_access_crm_settings()
    {
        $superAdmin = $this->userWithRole('super-admin');

        $this->actingAs($superAdmin)
            ->get('/admin/crm-settings')
            ->assertOk()
            ->assertSee('CRM Master Settings');
    }

    public function test_agent_cannot_access_crm_settings()
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get('/admin/crm-settings')
            ->assertForbidden();
    }

    public function test_admin_with_permission_can_create_setting_value()
    {
        $admin = $this->userWithRole('admin', ['crm_settings.view', 'crm_settings.manage']);

        $this->actingAs($admin)
            ->post('/admin/crm-settings/lead-statuses', [
                'name' => 'Warm Lead',
                'description' => 'Likely to convert',
                'color' => '#22c55e',
                'sort_order' => 7,
                'is_default' => '0',
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/crm-settings/lead-statuses');

        $this->assertDatabaseHas('crm_master_values', [
            'type' => 'lead_status',
            'slug' => 'warm-lead',
            'is_active' => true,
        ]);
    }

    public function test_setting_value_can_be_edited_and_deactivated()
    {
        $superAdmin = $this->userWithRole('super-admin');
        $value = CrmMasterValue::create([
            'type' => 'lead_source',
            'name' => 'Partner',
            'slug' => 'partner',
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->put("/admin/crm-settings/lead-sources/{$value->id}", [
                'name' => 'Partner Network',
                'slug' => 'partner-network',
                'color' => '#0d6efd',
                'sort_order' => 3,
                'is_default' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/crm-settings/lead-sources');

        $this->assertDatabaseHas('crm_master_values', [
            'id' => $value->id,
            'name' => 'Partner Network',
            'is_default' => true,
        ]);

        $this->actingAs($superAdmin)
            ->patch("/admin/crm-settings/lead-sources/{$value->id}/status")
            ->assertRedirect();

        $this->assertFalse($value->fresh()->is_active);
    }

    public function test_only_one_default_exists_per_type()
    {
        $superAdmin = $this->userWithRole('super-admin');
        $first = CrmMasterValue::create([
            'type' => 'activity_type',
            'name' => 'Call',
            'slug' => 'call',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->post('/admin/crm-settings/activity-types', [
                'name' => 'Meeting',
                'is_default' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/crm-settings/activity-types');

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue(CrmMasterValue::where('type', 'activity_type')->where('slug', 'meeting')->first()->is_default);
    }

    public function test_validation_errors_are_handled()
    {
        $superAdmin = $this->userWithRole('super-admin');

        $this->actingAs($superAdmin)
            ->post('/admin/crm-settings/loss-reasons', [
                'name' => '',
                'color' => 'red',
                'sort_order' => -1,
            ])
            ->assertSessionHasErrors(['name', 'color', 'sort_order']);
    }

    public function test_seeded_master_values_exist()
    {
        $this->seed();

        $this->assertDatabaseHas('crm_master_values', [
            'type' => 'lead_status',
            'slug' => 'new',
        ]);
        $this->assertDatabaseHas('crm_master_values', [
            'type' => 'opportunity_stage',
            'slug' => 'prospecting',
        ]);
        $this->assertDatabaseHas('permissions', [
            'slug' => 'crm_settings.manage',
        ]);
    }
}
