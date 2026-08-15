<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardWorkQueueTest extends TestCase
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

    private function leadFor(User $owner, array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Dashboard Lead',
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    private function activityFor(User $owner, array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'activity_type_id' => $this->masterValue('activity_type', 'Call')->id,
            'subject' => 'Dashboard Activity',
            'assigned_user_id' => $owner->id,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ], $overrides));
    }

    private function customerFor(User $owner, array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Dashboard Customer',
            'owner_id' => $owner->id,
            'is_active' => true,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    private function opportunityFor(User $owner, array $overrides = []): Opportunity
    {
        $customer = $overrides['customer'] ?? $this->customerFor($owner);
        unset($overrides['customer']);

        return Opportunity::create(array_merge([
            'name' => 'Dashboard Opportunity',
            'customer_id' => $customer->id,
            'stage_id' => $this->masterValue('opportunity_stage', 'Prospecting')->id,
            'owner_id' => $owner->id,
            'amount' => 100,
            'currency' => 'USD',
            'probability' => 25,
            'status' => 'open',
            'expected_close_date' => now()->addDays(10)->toDateString(),
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    public function test_agent_sees_own_work_only()
    {
        $agent = $this->userWithRole('agent');
        $otherAgent = $this->userWithRole('agent');
        $ownLead = $this->leadFor($agent, ['name' => 'Own Dashboard Lead', 'priority' => 'high']);
        $otherLead = $this->leadFor($otherAgent, ['name' => 'Hidden Dashboard Lead', 'priority' => 'high']);
        $ownActivity = $this->activityFor($agent, [
            'subject' => 'Own Dashboard Activity',
            'related_type' => Lead::class,
            'related_id' => $ownLead->id,
            'due_at' => now(),
        ]);
        $this->activityFor($otherAgent, [
            'subject' => 'Hidden Dashboard Activity',
            'related_type' => Lead::class,
            'related_id' => $otherLead->id,
            'due_at' => now(),
        ]);
        $ownOpportunity = $this->opportunityFor($agent, ['name' => 'Own Dashboard Deal']);
        $this->opportunityFor($otherAgent, ['name' => 'Hidden Dashboard Deal']);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($ownLead->name)
            ->assertSee($ownActivity->subject)
            ->assertSee($ownOpportunity->name)
            ->assertDontSee($otherLead->name)
            ->assertDontSee('Hidden Dashboard Activity')
            ->assertDontSee('Hidden Dashboard Deal');
    }

    public function test_manager_sees_permitted_team_and_subordinate_work()
    {
        $manager = $this->userWithRole('manager');
        $agent = $this->userWithRole('agent', [], ['reports_to_id' => $manager->id]);
        $team = Team::create(['name' => 'North Team', 'manager_id' => $manager->id, 'is_active' => true]);
        $team->members()->sync([$manager->id, $agent->id]);
        $lead = $this->leadFor($agent, ['name' => 'Subordinate Lead', 'priority' => 'high']);
        $activity = $this->activityFor($agent, [
            'subject' => 'Subordinate Follow Up',
            'related_type' => Lead::class,
            'related_id' => $lead->id,
            'due_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get('/dashboard?team='.$team->id)
            ->assertOk()
            ->assertSee($lead->name)
            ->assertSee($activity->subject);
    }

    public function test_admin_dashboard_loads()
    {
        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Daily Work Queue')
            ->assertSee('Pipeline Value');
    }

    public function test_overdue_and_today_follow_up_counts_are_correct()
    {
        $agent = $this->userWithRole('agent');
        $this->activityFor($agent, ['subject' => 'First Overdue', 'due_at' => now()->subDay()]);
        $this->activityFor($agent, ['subject' => 'Second Overdue', 'due_at' => now()->subHours(2)]);
        $this->activityFor($agent, ['subject' => 'Completed Past', 'status' => 'completed', 'due_at' => now()->subDay()]);
        $this->activityFor($agent, ['subject' => 'Today Follow Up', 'due_at' => now()]);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['Today\'s Activities', '1'])
            ->assertSeeInOrder(['Overdue Activities', '2'])
            ->assertSee('Today Follow Up');
    }

    public function test_opportunity_summary_uses_open_pipeline_value()
    {
        $agent = $this->userWithRole('agent');
        $this->opportunityFor($agent, ['name' => 'Open Value Deal', 'amount' => 100]);
        $this->opportunityFor($agent, ['name' => 'Won Value Deal', 'amount' => 200, 'status' => 'won']);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Open Value Deal')
            ->assertDontSee('Won Value Deal')
            ->assertSee('100.00');
    }

    public function test_work_queue_ordering_is_prioritized()
    {
        $agent = $this->userWithRole('agent');
        $this->activityFor($agent, ['subject' => 'A Overdue Call', 'due_at' => now()->subDay()]);
        $this->activityFor($agent, ['subject' => 'B Today Call', 'due_at' => now()]);
        $this->leadFor($agent, ['name' => 'C Urgent Lead', 'priority' => 'urgent']);
        $this->activityFor($agent, ['subject' => 'D Upcoming Call', 'due_at' => now()->addDays(2)]);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['A Overdue Call', 'B Today Call', 'C Urgent Lead', 'D Upcoming Call']);
    }

    public function test_unauthorized_records_do_not_appear()
    {
        $agent = $this->userWithRole('agent');
        $otherAgent = $this->userWithRole('agent');
        $this->leadFor($otherAgent, ['name' => 'Unauthorized Lead']);
        $this->activityFor($otherAgent, ['subject' => 'Unauthorized Activity', 'due_at' => now()]);
        $this->opportunityFor($otherAgent, ['name' => 'Unauthorized Opportunity']);

        $this->actingAs($agent)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Unauthorized Lead')
            ->assertDontSee('Unauthorized Activity')
            ->assertDontSee('Unauthorized Opportunity');
    }
}
