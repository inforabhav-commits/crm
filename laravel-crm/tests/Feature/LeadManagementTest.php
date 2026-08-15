<?php

namespace Tests\Feature;

use App\Models\CrmMasterValue;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadManagementTest extends TestCase
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

    private function status(string $name = 'New'): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => 'lead_status',
            'slug' => CrmMasterValue::makeSlug($name),
        ], [
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function source(string $name = 'Website'): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => 'lead_source',
            'slug' => CrmMasterValue::makeSlug($name),
        ], [
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function leadFor(User $owner, array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Acme Lead',
            'company' => 'Acme',
            'email' => 'lead@example.com',
            'phone' => '5551001000',
            'lead_status_id' => $this->status()->id,
            'lead_source_id' => $this->source()->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    public function test_authorized_lead_listing()
    {
        $admin = $this->userWithRole('super-admin');
        $owner = User::factory()->create(['is_active' => true]);
        $this->leadFor($owner, ['name' => 'Visible Lead']);

        $this->actingAs($admin)
            ->get('/leads')
            ->assertOk()
            ->assertSee('Visible Lead');
    }

    public function test_agent_record_visibility()
    {
        $agent = $this->userWithRole('agent', ['leads.view']);
        $otherAgent = $this->userWithRole('agent', ['leads.view']);

        $ownLead = $this->leadFor($agent, ['name' => 'Own Lead']);
        $otherLead = $this->leadFor($otherAgent, ['name' => 'Other Lead']);

        $this->actingAs($agent)
            ->get('/leads')
            ->assertOk()
            ->assertSee($ownLead->name)
            ->assertDontSee($otherLead->name);
    }

    public function test_create_lead()
    {
        $admin = $this->userWithRole('super-admin');
        $owner = User::factory()->create(['is_active' => true]);
        $status = $this->status('Contacted');
        $source = $this->source('Referral');

        $this->actingAs($admin)
            ->post('/leads', [
                'name' => 'New Buyer',
                'company' => 'Buyer Co',
                'email' => 'buyer@example.com',
                'phone' => '5552002000',
                'lead_status_id' => $status->id,
                'lead_source_id' => $source->id,
                'owner_id' => $owner->id,
                'priority' => 'high',
                'next_follow_up_at' => '2026-08-20 10:00:00',
                'notes' => 'Interested in demo.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'name' => 'New Buyer',
            'owner_id' => $owner->id,
            'lead_status_id' => $status->id,
            'lead_source_id' => $source->id,
            'created_by_id' => $admin->id,
        ]);
    }

    public function test_edit_lead()
    {
        $admin = $this->userWithRole('super-admin');
        $owner = User::factory()->create(['is_active' => true]);
        $lead = $this->leadFor($owner);
        $status = $this->status('Qualified');

        $this->actingAs($admin)
            ->put("/leads/{$lead->id}", [
                'name' => 'Updated Lead',
                'company' => 'Updated Co',
                'email' => 'updated@example.com',
                'phone' => '5553003000',
                'lead_status_id' => $status->id,
                'lead_source_id' => '',
                'owner_id' => $owner->id,
                'priority' => 'urgent',
                'notes' => 'Updated notes.',
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $lead->refresh();
        $this->assertSame('Updated Lead', $lead->name);
        $this->assertSame('urgent', $lead->priority);
        $this->assertSame($admin->id, $lead->updated_by_id);
    }

    public function test_validation_and_status_source_integration()
    {
        $admin = $this->userWithRole('super-admin');
        $inactiveStatus = CrmMasterValue::create([
            'type' => 'lead_status',
            'name' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post('/leads', [
                'name' => '',
                'email' => 'not-email',
                'lead_status_id' => $inactiveStatus->id,
                'priority' => 'impossible',
            ])
            ->assertSessionHasErrors(['name', 'email', 'lead_status_id', 'priority']);
    }

    public function test_unauthorized_access_prevention()
    {
        $agent = $this->userWithRole('agent', ['leads.view']);
        $otherAgent = $this->userWithRole('agent', ['leads.view']);
        $lead = $this->leadFor($otherAgent);

        $this->actingAs($agent)
            ->get("/leads/{$lead->id}")
            ->assertForbidden();
    }

    public function test_manager_can_view_direct_report_lead()
    {
        $manager = $this->userWithRole('manager', ['leads.view']);
        $agent = $this->userWithRole('agent', ['leads.view']);
        $agent->forceFill(['reports_to_id' => $manager->id])->save();
        $lead = $this->leadFor($agent, ['name' => 'Report Lead']);

        $this->actingAs($manager)
            ->get('/leads')
            ->assertOk()
            ->assertSee($lead->name);
    }
}
