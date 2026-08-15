<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CrmMasterValue;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivityManagementTest extends TestCase
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

    private function activityType(string $name = 'Call'): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => 'activity_type',
            'slug' => CrmMasterValue::makeSlug($name),
        ], [
            'name' => $name,
            'is_active' => true,
        ]);
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

    private function leadFor(User $owner, array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Activity Lead',
            'lead_status_id' => $this->status()->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    private function activityFor(User $owner, ?Lead $lead = null, array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'activity_type_id' => $this->activityType()->id,
            'subject' => 'Initial Call',
            'description' => 'Discuss needs.',
            'related_type' => $lead ? Lead::class : null,
            'related_id' => $lead?->id,
            'assigned_user_id' => $owner->id,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ], $overrides));
    }

    public function test_create_activity()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent');
        $lead = $this->leadFor($agent);
        $type = $this->activityType('Email');

        $this->actingAs($admin)
            ->post('/activities', [
                'activity_type_id' => $type->id,
                'subject' => 'Send proposal',
                'description' => 'Email pricing.',
                'lead_id' => $lead->id,
                'assigned_user_id' => $agent->id,
                'priority' => 'high',
                'status' => 'pending',
                'due_at' => '2026-08-20 10:00:00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'subject' => 'Send proposal',
            'related_type' => Lead::class,
            'related_id' => $lead->id,
            'assigned_user_id' => $agent->id,
            'created_by_id' => $admin->id,
        ]);
        $this->assertSame('2026-08-20 10:00:00', $lead->fresh()->next_follow_up_at->format('Y-m-d H:i:s'));
    }

    public function test_edit_activity()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent');
        $activity = $this->activityFor($agent, $this->leadFor($agent));
        $type = $this->activityType('Meeting');

        $this->actingAs($admin)
            ->put("/activities/{$activity->id}", [
                'activity_type_id' => $type->id,
                'subject' => 'Updated meeting',
                'description' => 'Meet onsite.',
                'lead_id' => $activity->related_id,
                'assigned_user_id' => $agent->id,
                'priority' => 'urgent',
                'status' => 'pending',
                'due_at' => '2026-08-21 11:00:00',
            ])
            ->assertRedirect("/activities/{$activity->id}");

        $activity->refresh();
        $this->assertSame('Updated meeting', $activity->subject);
        $this->assertSame('urgent', $activity->priority);
        $this->assertSame($admin->id, $activity->updated_by_id);
    }

    public function test_complete_activity()
    {
        $agent = $this->userWithRole('agent', ['activities.view', 'activities.complete']);
        $activity = $this->activityFor($agent);

        $this->actingAs($agent)
            ->patch("/activities/{$activity->id}/complete", [
                'outcome' => 'Customer answered.',
                'completion_notes' => 'Shared details.',
                'next_action' => 'Send quote.',
            ])
            ->assertRedirect("/activities/{$activity->id}");

        $activity->refresh();
        $this->assertSame('completed', $activity->status);
        $this->assertSame('Customer answered.', $activity->outcome);
        $this->assertNotNull($activity->completed_at);
    }

    public function test_overdue_detection()
    {
        $agent = $this->userWithRole('agent', ['activities.view']);
        $overdue = $this->activityFor($agent, null, [
            'subject' => 'Past call',
            'due_at' => now()->subDay(),
        ]);
        $this->activityFor($agent, null, [
            'subject' => 'Future call',
            'due_at' => now()->addDay(),
        ]);

        $this->assertTrue($overdue->fresh()->is_overdue);
        $this->actingAs($agent)
            ->get('/activities?due=overdue')
            ->assertOk()
            ->assertSee('Past call')
            ->assertDontSee('Future call');
    }

    public function test_follow_up_creation()
    {
        $agent = $this->userWithRole('agent', ['activities.create']);
        $lead = $this->leadFor($agent);
        $type = $this->activityType('Follow-up');

        $this->actingAs($agent)
            ->post('/activities', [
                'activity_type_id' => $type->id,
                'subject' => 'Follow up tomorrow',
                'lead_id' => $lead->id,
                'assigned_user_id' => $agent->id,
                'priority' => 'normal',
                'status' => 'pending',
                'due_at' => '2026-08-22 12:00:00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'subject' => 'Follow up tomorrow',
            'activity_type_id' => $type->id,
            'related_id' => $lead->id,
        ]);
    }

    public function test_next_follow_up_creation_after_completion()
    {
        $agent = $this->userWithRole('agent', ['activities.view', 'activities.complete']);
        $lead = $this->leadFor($agent);
        $activity = $this->activityFor($agent, $lead);
        $followUpType = $this->activityType('Follow-up');

        $this->actingAs($agent)
            ->patch("/activities/{$activity->id}/complete", [
                'outcome' => 'Asked for callback.',
                'next_action' => 'Call again.',
                'next_follow_up_at' => '2026-08-23 15:00:00',
                'next_follow_up_type_id' => $followUpType->id,
                'next_follow_up_subject' => 'Callback',
            ])
            ->assertRedirect("/activities/{$activity->id}");

        $this->assertDatabaseHas('activities', [
            'subject' => 'Callback',
            'activity_type_id' => $followUpType->id,
            'status' => 'pending',
            'related_type' => Lead::class,
            'related_id' => $lead->id,
        ]);
        $this->assertSame('2026-08-23 15:00:00', $lead->fresh()->next_follow_up_at->format('Y-m-d H:i:s'));
    }

    public function test_lead_activity_timeline()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'activities.view']);
        $lead = $this->leadFor($agent, ['name' => 'Timeline Lead']);
        $this->activityFor($agent, $lead, ['subject' => 'Timeline call']);

        $this->actingAs($agent)
            ->get("/leads/{$lead->id}")
            ->assertOk()
            ->assertSee('Timeline call');
    }

    public function test_agent_visibility()
    {
        $agent = $this->userWithRole('agent', ['activities.view']);
        $otherAgent = $this->userWithRole('agent', ['activities.view']);
        $own = $this->activityFor($agent, null, ['subject' => 'Own activity']);
        $other = $this->activityFor($otherAgent, null, ['subject' => 'Other activity']);

        $this->actingAs($agent)
            ->get('/activities')
            ->assertOk()
            ->assertSee($own->subject)
            ->assertDontSee($other->subject);
    }

    public function test_unauthorized_access_blocked()
    {
        $agent = $this->userWithRole('agent', ['activities.view']);
        $otherAgent = $this->userWithRole('agent', ['activities.view']);
        $activity = $this->activityFor($otherAgent);

        $this->actingAs($agent)
            ->get("/activities/{$activity->id}")
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_be_assigned()
    {
        $admin = $this->userWithRole('super-admin');
        $inactiveUser = $this->userWithRole('agent');
        $inactiveUser->forceFill(['is_active' => false])->save();
        $type = $this->activityType();

        $this->actingAs($admin)
            ->post('/activities', [
                'activity_type_id' => $type->id,
                'subject' => 'Invalid owner',
                'assigned_user_id' => $inactiveUser->id,
                'priority' => 'normal',
                'status' => 'pending',
                'due_at' => '2026-08-24 09:00:00',
            ])
            ->assertSessionHasErrors('assigned_user_id');
    }
}
