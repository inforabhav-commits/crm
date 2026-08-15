<?php

namespace Tests\Feature;

use App\Models\CrmMasterValue;
use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadAssignmentTest extends TestCase
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

    private function lead(?User $owner = null): Lead
    {
        $status = CrmMasterValue::firstOrCreate([
            'type' => 'lead_status',
            'slug' => 'new',
        ], [
            'name' => 'New',
            'is_active' => true,
        ]);

        return Lead::create([
            'name' => 'Assignment Lead',
            'lead_status_id' => $status->id,
            'owner_id' => $owner?->id,
            'priority' => 'normal',
            'created_by_id' => $owner?->id,
            'updated_by_id' => $owner?->id,
        ]);
    }

    private function activeTeamWithAgents(array $agents): Team
    {
        $team = Team::create([
            'name' => 'Assignment Team ' . uniqid(),
            'is_active' => true,
        ]);
        $team->members()->sync(collect($agents)->pluck('id')->all());

        return $team;
    }

    public function test_manual_assignment()
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

        $this->assertSame($agent->id, $lead->fresh()->owner_id);
        $this->assertDatabaseHas('lead_assignment_histories', [
            'lead_id' => $lead->id,
            'assigned_by_id' => $admin->id,
            'assigned_to_id' => $agent->id,
            'method' => 'manual',
        ]);
    }

    public function test_reassignment_preserves_history()
    {
        $admin = $this->userWithRole('super-admin');
        $firstAgent = $this->userWithRole('agent');
        $secondAgent = $this->userWithRole('agent');
        $lead = $this->lead($firstAgent);

        $this->actingAs($admin)
            ->post("/leads/{$lead->id}/assign", [
                'assignment_method' => 'manual',
                'owner_id' => $secondAgent->id,
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $this->assertSame($secondAgent->id, $lead->fresh()->owner_id);
        $this->assertDatabaseHas('lead_assignment_histories', [
            'lead_id' => $lead->id,
            'assigned_from_id' => $firstAgent->id,
            'assigned_to_id' => $secondAgent->id,
        ]);
        $this->assertSame(1, LeadAssignmentHistory::where('lead_id', $lead->id)->count());
    }

    public function test_round_robin_rotation()
    {
        $admin = $this->userWithRole('super-admin');
        $firstAgent = $this->userWithRole('agent');
        $secondAgent = $this->userWithRole('agent');
        $team = $this->activeTeamWithAgents([$firstAgent, $secondAgent]);
        $firstLead = $this->lead();
        $secondLead = $this->lead();
        $thirdLead = $this->lead();

        foreach ([$firstLead, $secondLead, $thirdLead] as $lead) {
            $this->actingAs($admin)
                ->post("/leads/{$lead->id}/assign", [
                    'assignment_method' => 'round_robin',
                    'team_id' => $team->id,
                ])
                ->assertRedirect("/leads/{$lead->id}");
        }

        $this->assertSame($firstAgent->id, $firstLead->fresh()->owner_id);
        $this->assertSame($secondAgent->id, $secondLead->fresh()->owner_id);
        $this->assertSame($firstAgent->id, $thirdLead->fresh()->owner_id);
    }

    public function test_inactive_agent_skipped()
    {
        $admin = $this->userWithRole('super-admin');
        $inactiveAgent = $this->userWithRole('agent');
        $inactiveAgent->forceFill(['is_active' => false])->save();
        $activeAgent = $this->userWithRole('agent');
        $team = $this->activeTeamWithAgents([$inactiveAgent, $activeAgent]);
        $lead = $this->lead();

        $this->actingAs($admin)
            ->post("/leads/{$lead->id}/assign", [
                'assignment_method' => 'round_robin',
                'team_id' => $team->id,
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $this->assertSame($activeAgent->id, $lead->fresh()->owner_id);
    }

    public function test_inactive_team_is_rejected()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent');
        $team = $this->activeTeamWithAgents([$agent]);
        $team->forceFill(['is_active' => false])->save();
        $lead = $this->lead();

        $this->actingAs($admin)
            ->post("/leads/{$lead->id}/assign", [
                'assignment_method' => 'round_robin',
                'team_id' => $team->id,
            ])
            ->assertSessionHasErrors('team_id');
    }

    public function test_unauthorized_assignment_blocked()
    {
        $agent = $this->userWithRole('agent', ['leads.view']);
        $targetAgent = $this->userWithRole('agent');
        $lead = $this->lead($agent);

        $this->actingAs($agent)
            ->post("/leads/{$lead->id}/assign", [
                'assignment_method' => 'manual',
                'owner_id' => $targetAgent->id,
            ])
            ->assertForbidden();

        $this->assertSame($agent->id, $lead->fresh()->owner_id);
    }

    public function test_team_assignment_uses_first_eligible_agent()
    {
        $admin = $this->userWithRole('super-admin');
        $firstAgent = $this->userWithRole('agent');
        $secondAgent = $this->userWithRole('agent');
        $team = $this->activeTeamWithAgents([$firstAgent, $secondAgent]);
        $lead = $this->lead();

        $this->actingAs($admin)
            ->post("/leads/{$lead->id}/assign", [
                'assignment_method' => 'team',
                'team_id' => $team->id,
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $this->assertSame($firstAgent->id, $lead->fresh()->owner_id);
        $this->assertDatabaseHas('lead_assignment_histories', [
            'lead_id' => $lead->id,
            'team_id' => $team->id,
            'method' => 'team',
        ]);
    }
}
