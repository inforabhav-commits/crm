<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportsTest extends TestCase
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

    private function user(string $roleSlug = 'agent', array $overrides = []): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => $roleSlug]);
        $permission = Permission::firstOrCreate(['slug' => 'reports.view'], ['name' => 'View reports']);
        $role->permissions()->syncWithoutDetaching($permission->id);
        $user = User::factory()->create(array_merge(['password' => Hash::make('password'), 'is_active' => true], $overrides));
        $user->roles()->sync([$role->id]);
        return $user;
    }

    private function value(string $type, string $name): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate(['type' => $type, 'slug' => CrmMasterValue::makeSlug($name)], ['name' => $name, 'is_active' => true]);
    }

    private function lead(User $owner, string $name, string $source, string $status = 'New', array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => $name,
            'phone' => '+155500'.random_int(10, 99),
            'owner_id' => $owner->id,
            'lead_status_id' => $this->value('lead_status', $status)->id,
            'lead_source_id' => $this->value('lead_source', $source)->id,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    private function opportunity(User $owner, string $name, string $status = 'open', float $amount = 100): Opportunity
    {
        $lead = $this->lead($owner, $name.' Lead', 'Web');
        $customer = Customer::create(['name' => $name.' Customer', 'phone' => '+15559999', 'owner_id' => $owner->id, 'is_active' => true]);
        return Opportunity::create([
            'name' => $name,
            'customer_id' => $customer->id,
            'source_lead_id' => $lead->id,
            'stage_id' => $this->value('opportunity_stage', 'Qualification')->id,
            'owner_id' => $owner->id,
            'amount' => $amount,
            'status' => $status,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
            'closed_at' => $status === 'open' ? null : now(),
        ]);
    }

    public function test_reports_require_permission_and_agents_only_see_permitted_data(): void
    {
        $agent = $this->user();
        $other = $this->user();
        $this->lead($agent, 'Visible Lead', 'Web');
        $this->lead($other, 'Hidden Lead', 'Partner');

        $this->actingAs($agent)->get('/reports')->assertOk()->assertSee('Lead funnel')->assertDontSee('Hidden Lead')->assertSee($agent->name);

        $noAccess = User::factory()->create(['is_active' => true]);
        $this->actingAs($noAccess)->get('/reports')->assertForbidden();
    }

    public function test_manager_team_visibility_and_filtering_are_respected(): void
    {
        $manager = $this->user('manager');
        $agent = $this->user('agent', ['reports_to_id' => $manager->id]);
        $other = $this->user('agent');
        $team = Team::create(['name' => 'Revenue Team', 'manager_id' => $manager->id, 'is_active' => true]);
        $team->members()->attach([$manager->id, $agent->id]);
        $this->lead($agent, 'Team Lead', 'Web');
        $this->lead($other, 'Outside Lead', 'Web');

        $this->actingAs($manager)->get('/reports?team='.$team->id)->assertOk()->assertSee('Agent performance')->assertSee($agent->name)->assertDontSee($other->name);
    }

    public function test_funnel_source_pipeline_and_won_lost_totals_use_real_data(): void
    {
        $agent = $this->user();
        $this->lead($agent, 'Web New', 'Web', 'New');
        $this->lead($agent, 'Partner Qualified', 'Partner', 'Qualified');
        $this->opportunity($agent, 'Open Deal', 'open', 250);
        $this->opportunity($agent, 'Won Deal', 'won', 400);
        $this->opportunity($agent, 'Lost Deal', 'lost', 150);

        $this->actingAs($agent)->get('/reports')
            ->assertOk()
            ->assertSee('Lead funnel')
            ->assertSee('Lead source performance')
            ->assertSee('Won / Lost opportunities')
            ->assertSee('250.00')
            ->assertSee('400.00');
    }

    public function test_activity_and_call_filters_only_count_matching_rows(): void
    {
        $agent = $this->user();
        $type = $this->value('activity_type', 'Call');
        Activity::create(['activity_type_id' => $type->id, 'subject' => 'Filtered Follow Up', 'assigned_user_id' => $agent->id, 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id, 'status' => 'pending', 'priority' => 'normal', 'due_at' => now()]);
        Activity::create(['activity_type_id' => $type->id, 'subject' => 'Completed Follow Up', 'assigned_user_id' => $agent->id, 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id, 'status' => 'completed', 'priority' => 'normal', 'due_at' => now()]);
        CallLog::create(['provider' => 'justcall', 'external_call_id' => 'report-inbound', 'direction' => 'inbound', 'status' => 'answered', 'user_id' => $agent->id, 'last_event_at' => now()]);
        CallLog::create(['provider' => 'justcall', 'external_call_id' => 'report-outbound', 'direction' => 'outbound', 'status' => 'completed', 'user_id' => $agent->id, 'last_event_at' => now()]);

        $this->actingAs($agent)->get('/reports?activity_status=pending&direction=inbound&call_status=answered')
            ->assertOk()
            ->assertSee('Activity / follow-up report')
            ->assertSee('Call activity report');
    }
}
