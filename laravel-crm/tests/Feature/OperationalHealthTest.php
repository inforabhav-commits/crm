<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookInboxEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $permissions = []): User
    {
        $roleModel = Role::firstOrCreate(['slug' => $role], ['name' => $role]);
        foreach ($permissions as $permission) {
            $roleModel->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission])->id);
        }
        $user = User::factory()->create(['password' => Hash::make('password'), 'is_active' => true]);
        $user->roles()->sync([$roleModel->id]);
        return $user;
    }

    public function test_health_page_requires_ops_permission(): void
    {
        $admin = $this->user('ops-admin', ['ops.view']);
        $agent = $this->user('agent');

        $this->actingAs($admin)->get('/admin/health')->assertOk()->assertSee('Operational Health');
        $this->actingAs($agent)->get('/admin/health')->assertForbidden();
    }

    public function test_health_reports_safe_warning_states_without_secrets(): void
    {
        Config::set('justcall.enabled', true);
        Config::set('justcall.api_key', 'health-api-key');
        Config::set('justcall.api_secret', 'health-api-secret');
        Config::set('justcall.webhook_secret', 'health-webhook-secret');
        $admin = $this->user('ops-admin', ['ops.view']);
        WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => 'call.completed',
            'external_id' => 'health-entry',
            'payload_hash' => hash('sha256', 'health-entry'),
            'payload' => ['type' => 'call.completed'],
            'received_at' => now(),
            'processing_status' => 'pending',
            'attempt_count' => 0,
        ]);

        $response = $this->actingAs($admin)->get('/admin/health')->assertOk()->assertSee('Warning')->assertSee('pending');
        $this->assertStringNotContainsString('health-api-key', $response->getContent());
        $this->assertStringNotContainsString('health-api-secret', $response->getContent());
        $this->assertStringNotContainsString('health-webhook-secret', $response->getContent());
    }
}
