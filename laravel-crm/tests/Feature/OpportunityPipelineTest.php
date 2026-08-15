<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OpportunityPipelineTest extends TestCase
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

        $user = User::factory()->create(['password' => Hash::make('password'), 'is_active' => true]);
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

    private function seedMasters(): void
    {
        foreach (['Prospecting', 'Proposal', 'Negotiation', 'Won', 'Lost'] as $index => $stage) {
            $this->masterValue('opportunity_stage', $stage, $index + 1);
        }
        $this->masterValue('loss_reason', 'Budget');
        $this->masterValue('activity_type', 'Call');
        $this->masterValue('lead_status', 'New');
        $this->masterValue('lead_status', 'Converted', 4);
    }

    private function customerFor(User $owner): Customer
    {
        return Customer::create([
            'name' => 'Acme Account',
            'email' => 'acme@example.com',
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);
    }

    private function opportunityFor(User $owner, array $overrides = []): Opportunity
    {
        $customer = $overrides['customer'] ?? $this->customerFor($owner);
        unset($overrides['customer']);

        return Opportunity::create(array_merge([
            'name' => 'Acme Deal',
            'customer_id' => $customer->id,
            'stage_id' => $this->masterValue('opportunity_stage', 'Prospecting')->id,
            'owner_id' => $owner->id,
            'amount' => 25000,
            'currency' => 'USD',
            'probability' => 10,
            'status' => 'open',
            'expected_close_date' => '2026-09-30',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    public function test_opportunity_crud()
    {
        $this->seedMasters();
        $admin = $this->userWithRole('super-admin');
        $owner = $this->userWithRole('agent');
        $customer = $this->customerFor($owner);
        $contact = Contact::create(['customer_id' => $customer->id, 'first_name' => 'Riya', 'is_active' => true]);
        $proposal = $this->masterValue('opportunity_stage', 'Proposal', 2);

        $this->actingAs($admin)
            ->post('/opportunities', [
                'name' => 'New Pipeline Deal',
                'customer_id' => $customer->id,
                'contact_id' => $contact->id,
                'stage_id' => $proposal->id,
                'owner_id' => $owner->id,
                'amount' => 50000,
                'currency' => 'INR',
                'probability' => 55,
                'expected_close_date' => '2026-10-01',
                'next_step' => 'Send proposal',
                'description' => 'Expansion deal',
                'notes' => 'High value',
            ])
            ->assertRedirect();

        $opportunity = Opportunity::where('name', 'New Pipeline Deal')->firstOrFail();
        $this->assertSame('INR', $opportunity->currency);
        $this->assertSame(55, $opportunity->probability);

        $this->actingAs($admin)
            ->put("/opportunities/{$opportunity->id}", [
                'name' => 'Updated Pipeline Deal',
                'customer_id' => $customer->id,
                'contact_id' => $contact->id,
                'stage_id' => $proposal->id,
                'owner_id' => $owner->id,
                'amount' => 60000,
                'currency' => 'USD',
                'probability' => 60,
                'next_step' => 'Schedule negotiation',
            ])
            ->assertRedirect("/opportunities/{$opportunity->id}");

        $this->assertSame('Updated Pipeline Deal', $opportunity->fresh()->name);
    }

    public function test_lead_converted_opportunity_works()
    {
        $this->seedMasters();
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.convert', 'opportunities.view']);
        $lead = Lead::create([
            'name' => 'Convert Lead',
            'company' => 'Converted Co',
            'email' => 'convert@example.com',
            'phone' => '5551002000',
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $agent->id,
            'priority' => 'normal',
            'qualification_status' => 'qualified',
            'qualification_need' => 'Pipeline need',
            'qualification_authority' => 'Decision maker',
            'qualification_timeline' => 'This month',
            'qualification_interest_level' => 'high',
            'qualified_at' => now(),
            'qualified_by_id' => $agent->id,
        ]);

        $this->actingAs($agent)
            ->post("/leads/{$lead->id}/convert", [
                'customer_name' => 'Converted Co',
                'customer_email' => 'convert@example.com',
                'customer_phone' => '5551002000',
                'contact_first_name' => 'Convert',
                'contact_email' => 'convert@example.com',
                'opportunity_name' => 'Converted Opportunity',
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $opportunity = $lead->fresh()->convertedOpportunity;
        $this->actingAs($agent)
            ->get("/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertSee('Converted Opportunity');
    }

    public function test_stage_movement_and_history()
    {
        $this->seedMasters();
        $agent = $this->userWithRole('agent', ['opportunities.view', 'opportunities.change_stage']);
        $opportunity = $this->opportunityFor($agent);
        $proposal = $this->masterValue('opportunity_stage', 'Proposal', 2);

        $this->actingAs($agent)
            ->patch("/opportunities/{$opportunity->id}/stage", [
                'stage_id' => $proposal->id,
                'stage_notes' => 'Advanced to proposal',
            ])
            ->assertRedirect("/opportunities/{$opportunity->id}");

        $opportunity->refresh();
        $this->assertSame($proposal->id, $opportunity->stage_id);
        $this->assertDatabaseHas('opportunity_stage_histories', [
            'opportunity_id' => $opportunity->id,
            'from_stage_id' => $this->masterValue('opportunity_stage', 'Prospecting')->id,
            'to_stage_id' => $proposal->id,
            'notes' => 'Advanced to proposal',
        ]);
    }

    public function test_won_handling()
    {
        $this->seedMasters();
        $agent = $this->userWithRole('agent', ['opportunities.view', 'opportunities.change_stage']);
        $opportunity = $this->opportunityFor($agent);
        $won = $this->masterValue('opportunity_stage', 'Won', 4);

        $this->actingAs($agent)
            ->patch("/opportunities/{$opportunity->id}/stage", ['stage_id' => $won->id])
            ->assertRedirect("/opportunities/{$opportunity->id}");

        $opportunity->refresh();
        $this->assertSame('won', $opportunity->status);
        $this->assertSame(100, $opportunity->probability);
        $this->assertNotNull($opportunity->won_at);
    }

    public function test_lost_requires_loss_reason()
    {
        $this->seedMasters();
        $agent = $this->userWithRole('agent', ['opportunities.view', 'opportunities.change_stage']);
        $opportunity = $this->opportunityFor($agent);
        $lost = $this->masterValue('opportunity_stage', 'Lost', 5);

        $this->actingAs($agent)
            ->patch("/opportunities/{$opportunity->id}/stage", ['stage_id' => $lost->id])
            ->assertSessionHasErrors('loss_reason_id');

        $reason = $this->masterValue('loss_reason', 'Budget');
        $this->actingAs($agent)
            ->patch("/opportunities/{$opportunity->id}/stage", ['stage_id' => $lost->id, 'loss_reason_id' => $reason->id])
            ->assertRedirect("/opportunities/{$opportunity->id}");

        $this->assertSame('lost', $opportunity->fresh()->status);
        $this->assertSame($reason->id, $opportunity->fresh()->loss_reason_id);
    }

    public function test_pipeline_kanban_visibility_and_agent_ownership()
    {
        $this->seedMasters();
        $agent = $this->userWithRole('agent', ['opportunities.view']);
        $otherAgent = $this->userWithRole('agent', ['opportunities.view']);
        $own = $this->opportunityFor($agent, ['name' => 'Visible Deal']);
        $other = $this->opportunityFor($otherAgent, ['name' => 'Hidden Deal']);

        $this->actingAs($agent)
            ->get('/opportunities/pipeline')
            ->assertOk()
            ->assertSee($own->name)
            ->assertDontSee($other->name);
    }

    public function test_unauthorized_access_blocked()
    {
        $this->seedMasters();
        $agent = $this->userWithRole('agent', ['opportunities.view']);
        $otherAgent = $this->userWithRole('agent', ['opportunities.view']);
        $opportunity = $this->opportunityFor($otherAgent);

        $this->actingAs($agent)
            ->get("/opportunities/{$opportunity->id}")
            ->assertForbidden();
    }

    public function test_opportunity_activities_work()
    {
        $this->seedMasters();
        $agent = $this->userWithRole('agent', ['opportunities.view', 'activities.view']);
        $opportunity = $this->opportunityFor($agent);

        Activity::create([
            'activity_type_id' => $this->masterValue('activity_type', 'Call')->id,
            'subject' => 'Opportunity follow-up',
            'related_type' => Opportunity::class,
            'related_id' => $opportunity->id,
            'assigned_user_id' => $agent->id,
            'created_by_id' => $agent->id,
            'updated_by_id' => $agent->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($agent)
            ->get("/opportunities/{$opportunity->id}")
            ->assertOk()
            ->assertSee('Opportunity follow-up');
    }
}
