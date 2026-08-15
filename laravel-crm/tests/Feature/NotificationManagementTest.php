<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-16 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    private function masterValue(string $type, string $name, int $sort = 1): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => $type,
            'slug' => CrmMasterValue::makeSlug($name),
        ], [
            'name' => $name,
            'sort_order' => $sort,
            'is_active' => true,
            'is_default' => $sort === 1,
        ]);
    }

    private function lead(?User $owner = null, array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Notification Lead',
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner?->id,
            'priority' => 'normal',
            'created_by_id' => $owner?->id,
            'updated_by_id' => $owner?->id,
        ], $overrides));
    }

    private function activityFor(User $owner, array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'activity_type_id' => $this->masterValue('activity_type', 'Call')->id,
            'subject' => 'Notification Activity',
            'assigned_user_id' => $owner->id,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ], $overrides));
    }

    private function opportunityFor(User $owner, array $overrides = []): Opportunity
    {
        $customer = Customer::create([
            'name' => 'Notification Customer',
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);

        return Opportunity::create(array_merge([
            'name' => 'Notification Opportunity',
            'customer_id' => $customer->id,
            'stage_id' => $this->masterValue('opportunity_stage', 'Prospecting')->id,
            'owner_id' => $owner->id,
            'amount' => 1000,
            'currency' => 'USD',
            'probability' => 10,
            'status' => 'open',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    public function test_lead_assignment_notification()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent', ['leads.view']);
        $lead = $this->lead(null, ['name' => 'Assigned Notification Lead']);

        $this->actingAs($admin)
            ->post("/leads/{$lead->id}/assign", [
                'assignment_method' => 'manual',
                'owner_id' => $agent->id,
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $this->assertSame(1, $agent->notifications()->count());
        $notification = $agent->notifications()->first();
        $this->assertSame('lead_assigned', $notification->data['category']);
        $this->assertSame('Assigned Notification Lead', $notification->data['message']);
    }

    public function test_overdue_activity_notification()
    {
        $agent = $this->userWithRole('agent', ['activities.view']);
        $activity = $this->activityFor($agent, [
            'subject' => 'Overdue Notification Activity',
            'due_at' => now()->subDay(),
        ]);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('1');

        $notification = $agent->notifications()->first();
        $this->assertSame('activity_overdue', $notification->data['category']);
        $this->assertSame($activity->subject, $notification->data['message']);

        $this->actingAs($agent)->get('/dashboard')->assertOk();
        $this->assertSame(1, $agent->notifications()->count());
    }

    public function test_opportunity_update_notification()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent', ['opportunities.view']);
        $opportunity = $this->opportunityFor($agent);
        $proposal = $this->masterValue('opportunity_stage', 'Proposal', 2);

        $this->actingAs($admin)
            ->patch("/opportunities/{$opportunity->id}/stage", [
                'stage_id' => $proposal->id,
                'stage_notes' => 'Moved forward',
            ])
            ->assertRedirect("/opportunities/{$opportunity->id}");

        $notification = $agent->notifications()->first();
        $this->assertSame('opportunity_stage_changed', $notification->data['category']);
        $this->assertStringContainsString('Proposal', $notification->data['message']);
    }

    public function test_unread_count_and_mark_as_read()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent', ['leads.view']);
        $lead = $this->lead();

        $this->actingAs($admin)->post("/leads/{$lead->id}/assign", [
            'assignment_method' => 'manual',
            'owner_id' => $agent->id,
        ]);

        $notification = $agent->notifications()->first();

        $this->actingAs($agent)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Unread')
            ->assertSee('Notifications')
            ->assertSee('1');

        $this->actingAs($agent)
            ->patch("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertSame(0, $agent->fresh()->unreadNotifications()->count());
    }

    public function test_unauthorized_users_cannot_see_another_users_notifications()
    {
        $admin = $this->userWithRole('super-admin');
        $agent = $this->userWithRole('agent', ['leads.view']);
        $otherAgent = $this->userWithRole('agent', ['leads.view']);
        $lead = $this->lead();

        $this->actingAs($admin)->post("/leads/{$lead->id}/assign", [
            'assignment_method' => 'manual',
            'owner_id' => $agent->id,
        ]);

        $notification = $agent->notifications()->first();

        $this->actingAs($otherAgent)
            ->get('/notifications')
            ->assertOk()
            ->assertDontSee('New lead assigned to you');

        $this->actingAs($otherAgent)
            ->patch("/notifications/{$notification->id}/read")
            ->assertNotFound();
    }
}
