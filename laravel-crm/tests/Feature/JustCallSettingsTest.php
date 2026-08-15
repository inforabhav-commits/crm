<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Integrations\JustCall\JustCallConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JustCallSettingsTest extends TestCase
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

    private function configureJustCall(array $overrides = []): void
    {
        Config::set('justcall', array_merge([
            'enabled' => true,
            'base_url' => 'https://api.justcall.test',
            'auth_mode' => 'basic',
            'api_key' => 'test-api-key',
            'api_secret' => 'test-api-secret',
            'webhook_secret' => 'test-webhook-secret',
            'test_endpoint' => '/v2.1/users',
        ], $overrides));
    }

    public function test_authorized_admin_can_view_settings()
    {
        $this->configureJustCall();
        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/justcall-settings')
            ->assertOk()
            ->assertSee('JustCall Integration')
            ->assertSee('Credentials Ready');
    }

    public function test_unauthorized_agent_cannot_access_settings()
    {
        $agent = $this->userWithRole('agent');

        $this->actingAs($agent)
            ->get('/admin/justcall-settings')
            ->assertForbidden();
    }

    public function test_config_loads_from_environment_backed_config()
    {
        $this->configureJustCall([
            'enabled' => false,
            'base_url' => 'https://custom.justcall.test',
            'api_key' => 'configured-key',
            'api_secret' => 'configured-secret',
            'webhook_secret' => '',
        ]);

        $status = JustCallConfig::fromConfig()->status();

        $this->assertFalse($status['enabled']);
        $this->assertSame('https://custom.justcall.test', $status['base_url']);
        $this->assertTrue($status['api_key_configured']);
        $this->assertTrue($status['api_secret_configured']);
        $this->assertFalse($status['webhook_secret_configured']);
    }

    public function test_secrets_are_not_rendered()
    {
        $this->configureJustCall();
        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/justcall-settings')
            ->assertOk()
            ->assertDontSee('test-api-key')
            ->assertDontSee('test-api-secret')
            ->assertDontSee('test-webhook-secret')
            ->assertSee('Masked server-side');
    }

    public function test_connection_test_handles_success_safely()
    {
        $this->configureJustCall();
        $admin = $this->userWithRole('super-admin');
        Http::fake([
            'https://api.justcall.test/v2.1/users' => Http::response(['data' => []], 200),
        ]);

        $this->actingAs($admin)
            ->post('/admin/justcall-settings/test')
            ->assertRedirect()
            ->assertSessionHas('status', 'JustCall connection successful.');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.justcall.test/v2.1/users');
        $audit = AuditLog::where('action', 'justcall.connection_tested')->firstOrFail();
        $this->assertTrue($audit->new_values['ok']);
    }

    public function test_connection_test_handles_failure_safely()
    {
        $this->configureJustCall();
        $admin = $this->userWithRole('super-admin');
        Http::fake([
            'https://api.justcall.test/v2.1/users' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->actingAs($admin)
            ->post('/admin/justcall-settings/test')
            ->assertRedirect()
            ->assertSessionHas('error', 'JustCall returned HTTP 401.');

        $audit = AuditLog::where('action', 'justcall.connection_tested')->firstOrFail();
        $this->assertFalse($audit->new_values['ok']);
        $this->assertSame(401, $audit->new_values['status']);
    }

    public function test_audit_log_does_not_store_secrets()
    {
        $this->configureJustCall();
        $admin = $this->userWithRole('super-admin');
        Http::fake([
            'https://api.justcall.test/v2.1/users' => Http::response(['data' => []], 200),
        ]);

        $this->actingAs($admin)->post('/admin/justcall-settings/test');

        $payload = AuditLog::where('action', 'justcall.connection_tested')->firstOrFail()->toArray();
        $encoded = json_encode($payload);

        $this->assertStringNotContainsString('test-api-key', $encoded);
        $this->assertStringNotContainsString('test-api-secret', $encoded);
        $this->assertStringNotContainsString('test-webhook-secret', $encoded);
    }
}
