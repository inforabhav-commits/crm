<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\JustCallUserMapping;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JustCallUserMappingTest extends TestCase
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

    private function userWithRole(string $roleSlug, array $permissions = [], array $overrides = []): User
    {
        $role = $this->role($roleSlug);
        foreach ($permissions as $permission) {
            $role->permissions()->syncWithoutDetaching($this->permission($permission)->id);
        }

        $user = User::factory()->create(array_merge([
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $overrides));
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function configureJustCall(): void
    {
        Config::set('justcall', [
            'enabled' => true,
            'base_url' => 'https://api.justcall.test',
            'auth_mode' => 'basic',
            'api_key' => 'mapping-api-key',
            'api_secret' => 'mapping-api-secret',
            'webhook_secret' => 'mapping-webhook-secret',
            'test_endpoint' => '/v2.1/users',
        ]);
    }

    public function test_authorized_admin_can_manage_mappings()
    {
        $admin = $this->userWithRole('super-admin');
        $crmUser = $this->userWithRole('agent');

        $this->actingAs($admin)
            ->get('/admin/justcall-settings/mappings')
            ->assertOk()
            ->assertSee('JustCall User Mapping');

        $this->actingAs($admin)
            ->post('/admin/justcall-settings/mappings', [
                'user_id' => $crmUser->id,
                'justcall_user_id' => 'jc-100',
                'justcall_name' => 'Mapped Agent',
                'justcall_email' => $crmUser->email,
                'justcall_phone' => '+15550100',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('just_call_user_mappings', [
            'user_id' => $crmUser->id,
            'justcall_user_id' => 'jc-100',
            'is_active' => true,
        ]);
    }

    public function test_unauthorized_agent_cannot_manage_mappings()
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get('/admin/justcall-settings/mappings')
            ->assertForbidden();

        $this->actingAs($agent)
            ->post('/admin/justcall-settings/mappings', [
                'user_id' => $agent->id,
                'justcall_user_id' => 'jc-agent',
            ])
            ->assertForbidden();
    }

    public function test_crm_user_maps_to_justcall_user()
    {
        $admin = $this->userWithRole('super-admin');
        $crmUser = $this->userWithRole('agent');

        $this->actingAs($admin)->post('/admin/justcall-settings/mappings', [
            'user_id' => $crmUser->id,
            'justcall_user_id' => 'external-1',
            'justcall_name' => 'External User',
            'justcall_email' => 'external@example.com',
        ]);

        $mapping = $crmUser->fresh()->justCallMapping;
        $this->assertNotNull($mapping);
        $this->assertSame('external-1', $mapping->justcall_user_id);
        $this->assertSame($crmUser->id, $mapping->active_user_id);
        $this->assertSame('external-1', $mapping->active_justcall_user_id);
    }

    public function test_duplicate_active_mapping_is_rejected()
    {
        $admin = $this->userWithRole('super-admin');
        $firstUser = $this->userWithRole('agent');
        $secondUser = $this->userWithRole('agent');

        JustCallUserMapping::create([
            'user_id' => $firstUser->id,
            'justcall_user_id' => 'jc-duplicate',
            'active_user_id' => $firstUser->id,
            'active_justcall_user_id' => 'jc-duplicate',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post('/admin/justcall-settings/mappings', [
                'user_id' => $secondUser->id,
                'justcall_user_id' => 'jc-duplicate',
            ])
            ->assertStatus(422);
    }

    public function test_inactive_crm_user_cannot_be_used_as_active_mapping()
    {
        $admin = $this->userWithRole('super-admin');
        $inactive = $this->userWithRole('agent', [], ['is_active' => false]);

        $this->actingAs($admin)
            ->post('/admin/justcall-settings/mappings', [
                'user_id' => $inactive->id,
                'justcall_user_id' => 'jc-inactive',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_email_based_suggested_match_works()
    {
        $admin = $this->userWithRole('super-admin');
        $crmUser = $this->userWithRole('agent', [], ['email' => 'match@example.com']);

        $this->actingAs($admin)
            ->withSession([
                'justcall_users' => [
                    'ok' => true,
                    'status' => 200,
                    'message' => 'JustCall users fetched.',
                    'users' => [
                        ['id' => 'jc-match', 'name' => 'Different Name', 'email' => $crmUser->email, 'phone' => '+15550111'],
                    ],
                ],
            ])
            ->get('/admin/justcall-settings/mappings')
            ->assertOk()
            ->assertSee('Suggested Matches')
            ->assertSee('Email')
            ->assertSee('jc-match');
    }

    public function test_ambiguous_match_is_not_silently_saved()
    {
        $admin = $this->userWithRole('super-admin');
        $crmUser = $this->userWithRole('agent', [], ['email' => 'ambiguous@example.com']);

        $this->actingAs($admin)
            ->withSession([
                'justcall_users' => [
                    'ok' => true,
                    'status' => 200,
                    'message' => 'JustCall users fetched.',
                    'users' => [
                        ['id' => 'jc-one', 'name' => 'One', 'email' => $crmUser->email, 'phone' => null],
                        ['id' => 'jc-two', 'name' => 'Two', 'email' => $crmUser->email, 'phone' => null],
                    ],
                ],
            ])
            ->get('/admin/justcall-settings/mappings')
            ->assertOk()
            ->assertDontSee('Suggested Matches');

        $this->assertDatabaseCount('just_call_user_mappings', 0);
    }

    public function test_verify_mapping_updates_safe_status_and_audit()
    {
        $this->configureJustCall();
        $admin = $this->userWithRole('super-admin');
        $crmUser = $this->userWithRole('agent');
        $mapping = JustCallUserMapping::create([
            'user_id' => $crmUser->id,
            'justcall_user_id' => 'jc-verify',
            'active_user_id' => $crmUser->id,
            'active_justcall_user_id' => 'jc-verify',
            'is_active' => true,
        ]);
        Http::fake([
            'https://api.justcall.test/v2.1/users' => Http::response([
                'data' => [
                    ['id' => 'jc-verify', 'name' => 'Verified Agent', 'email' => 'verified@example.com', 'phone_number' => '+15550199'],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)
            ->post("/admin/justcall-settings/mappings/{$mapping->id}/verify")
            ->assertRedirect()
            ->assertSessionHas('status', 'JustCall mapping verified.');

        $mapping->refresh();
        $this->assertSame('Verified Agent', $mapping->justcall_name);
        $this->assertNotNull($mapping->last_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'justcall.mapping_verified',
            'entity_type' => JustCallUserMapping::class,
            'entity_id' => $mapping->id,
        ]);
    }

    public function test_audit_logs_are_created_safely()
    {
        $admin = $this->userWithRole('super-admin');
        $crmUser = $this->userWithRole('agent');

        $this->actingAs($admin)->post('/admin/justcall-settings/mappings', [
            'user_id' => $crmUser->id,
            'justcall_user_id' => 'jc-safe',
            'justcall_name' => 'Safe Agent',
            'justcall_email' => 'safe@example.com',
        ]);

        $payload = AuditLog::where('action', 'justcall.mapping_created')->firstOrFail()->toArray();
        $encoded = json_encode($payload);

        $this->assertStringNotContainsString('mapping-api-key', $encoded);
        $this->assertStringNotContainsString('mapping-api-secret', $encoded);
        $this->assertStringNotContainsString('mapping-webhook-secret', $encoded);
    }
}
