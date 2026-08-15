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

class LeadQualificationConversionTest extends TestCase
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

    private function masterValue(string $type, string $name): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => $type,
            'slug' => CrmMasterValue::makeSlug($name),
        ], [
            'name' => $name,
            'is_active' => true,
            'is_default' => $name === 'Prospecting' || $name === 'New',
        ]);
    }

    private function leadFor(User $owner, array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Riya Sharma',
            'company' => 'Acme India',
            'email' => 'riya@example.com',
            'phone' => '+91 98765 43210',
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    private function qualify(Lead $lead, User $user): void
    {
        $lead->forceFill([
            'qualification_status' => 'qualified',
            'qualification_need' => 'Needs CRM automation.',
            'qualification_budget' => 15000,
            'qualification_authority' => 'Decision maker confirmed',
            'qualification_timeline' => 'This quarter',
            'qualification_interest_level' => 'high',
            'qualification_notes' => 'Strong fit.',
            'qualified_at' => now(),
            'qualified_by_id' => $user->id,
        ])->save();
    }

    private function conversionPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Acme India',
            'customer_company' => 'Acme India',
            'customer_email' => 'riya@example.com',
            'customer_phone' => '+91 98765 43210',
            'contact_first_name' => 'Riya',
            'contact_last_name' => 'Sharma',
            'contact_email' => 'riya@example.com',
            'contact_phone' => '+91 98765 43210',
            'opportunity_name' => 'Acme India Opportunity',
            'opportunity_amount' => 15000,
            'expected_close_date' => '2026-09-30',
        ], $overrides);
    }

    private function seedConversionMasters(): void
    {
        $this->masterValue('lead_status', 'Converted');
        $this->masterValue('opportunity_stage', 'Prospecting');
        $this->masterValue('activity_type', 'Call');
    }

    public function test_lead_qualification_works()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.qualify']);
        $lead = $this->leadFor($agent);

        $this->actingAs($agent)
            ->patch("/leads/{$lead->id}/qualify", [
                'qualification_status' => 'qualified',
                'qualification_need' => 'Needs CRM automation.',
                'qualification_budget' => '12500',
                'qualification_authority' => 'Owner approved',
                'qualification_timeline' => '30 days',
                'qualification_interest_level' => 'high',
                'qualification_notes' => 'Ready for proposal.',
            ])
            ->assertRedirect("/leads/{$lead->id}");

        $lead->refresh();
        $this->assertSame('qualified', $lead->qualification_status);
        $this->assertNotNull($lead->qualified_at);
        $this->assertSame($agent->id, $lead->qualified_by_id);
    }

    public function test_unqualified_or_incomplete_lead_cannot_convert()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.convert']);
        $lead = $this->leadFor($agent);
        $this->seedConversionMasters();

        $this->actingAs($agent)
            ->post("/leads/{$lead->id}/convert", $this->conversionPayload())
            ->assertSessionHasErrors('conversion');

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_qualified_lead_converts_successfully()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.convert']);
        $lead = $this->leadFor($agent);
        $this->qualify($lead, $agent);
        $this->seedConversionMasters();

        $this->actingAs($agent)
            ->post("/leads/{$lead->id}/convert", $this->conversionPayload())
            ->assertRedirect("/leads/{$lead->id}");

        $lead->refresh();
        $this->assertNotNull($lead->converted_at);
        $this->assertSame($agent->id, $lead->converted_by_id);
        $this->assertNotNull($lead->converted_customer_id);
        $this->assertNotNull($lead->converted_contact_id);
        $this->assertNotNull($lead->converted_opportunity_id);
        $this->assertSame('Converted', $lead->status->name);

        $this->assertDatabaseHas('customers', [
            'id' => $lead->converted_customer_id,
            'converted_from_lead_id' => $lead->id,
        ]);
        $this->assertDatabaseHas('contacts', [
            'id' => $lead->converted_contact_id,
            'source_lead_id' => $lead->id,
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('opportunities', [
            'id' => $lead->converted_opportunity_id,
            'source_lead_id' => $lead->id,
        ]);
    }

    public function test_existing_clear_customer_and_contact_match_are_reused()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.convert']);
        $lead = $this->leadFor($agent);
        $this->qualify($lead, $agent);
        $this->seedConversionMasters();
        $customer = Customer::create([
            'name' => 'Existing Acme',
            'email' => 'riya@example.com',
            'phone' => '9876543210',
            'owner_id' => $agent->id,
            'is_active' => true,
        ]);
        $contact = Contact::create([
            'customer_id' => $customer->id,
            'first_name' => 'Existing',
            'email' => 'riya@example.com',
            'is_active' => true,
        ]);

        $this->actingAs($agent)
            ->post("/leads/{$lead->id}/convert", $this->conversionPayload())
            ->assertRedirect("/leads/{$lead->id}");

        $lead->refresh();
        $this->assertSame($customer->id, $lead->converted_customer_id);
        $this->assertSame($contact->id, $lead->converted_contact_id);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_source_relationships_and_activities_remain_available()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.convert', 'customers.view', 'contacts.view']);
        $lead = $this->leadFor($agent);
        $this->qualify($lead, $agent);
        $this->seedConversionMasters();
        $activity = Activity::create([
            'activity_type_id' => $this->masterValue('activity_type', 'Call')->id,
            'subject' => 'Pre-conversion call',
            'related_type' => Lead::class,
            'related_id' => $lead->id,
            'assigned_user_id' => $agent->id,
            'created_by_id' => $agent->id,
            'updated_by_id' => $agent->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($agent)->post("/leads/{$lead->id}/convert", $this->conversionPayload());
        $lead->refresh();

        $this->actingAs($agent)
            ->get("/leads/{$lead->id}")
            ->assertOk()
            ->assertSee($activity->subject)
            ->assertSee('Acme India Opportunity');

        $this->actingAs($agent)
            ->get("/customers/{$lead->converted_customer_id}")
            ->assertOk()
            ->assertSee($lead->name);

        $this->actingAs($agent)
            ->get("/contacts/{$lead->converted_contact_id}")
            ->assertOk()
            ->assertSee($lead->name);
    }

    public function test_repeat_conversion_blocked()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.convert']);
        $lead = $this->leadFor($agent);
        $this->qualify($lead, $agent);
        $this->seedConversionMasters();

        $this->actingAs($agent)->post("/leads/{$lead->id}/convert", $this->conversionPayload());

        $this->actingAs($agent)
            ->post("/leads/{$lead->id}/convert", $this->conversionPayload(['opportunity_name' => 'Duplicate']))
            ->assertSessionHasErrors('conversion');

        $this->assertDatabaseCount('opportunities', 1);
    }

    public function test_transaction_rolls_back_on_failure()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.convert']);
        $lead = $this->leadFor($agent, ['email' => 'rollback@example.com', 'phone' => '5559990000']);
        $this->qualify($lead, $agent);
        $this->seedConversionMasters();
        Opportunity::create([
            'name' => 'Conflicting Opportunity',
            'customer_id' => Customer::create(['name' => 'Existing', 'owner_id' => $agent->id, 'is_active' => true])->id,
            'source_lead_id' => $lead->id,
            'stage_id' => $this->masterValue('opportunity_stage', 'Prospecting')->id,
            'owner_id' => $agent->id,
        ]);

        $this->actingAs($agent)
            ->post("/leads/{$lead->id}/convert", $this->conversionPayload([
                'customer_email' => 'rollback@example.com',
                'customer_phone' => '5559990000',
                'contact_email' => 'rollback@example.com',
                'contact_phone' => '5559990000',
            ]))
            ->assertSessionHasErrors('conversion');

        $this->assertNull($lead->fresh()->converted_at);
        $this->assertDatabaseMissing('customers', ['email' => 'rollback@example.com']);
        $this->assertDatabaseMissing('contacts', ['email' => 'rollback@example.com']);
    }

    public function test_unauthorized_conversion_blocked()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.convert']);
        $otherAgent = $this->userWithRole('agent', ['leads.view', 'leads.convert']);
        $lead = $this->leadFor($otherAgent);
        $this->qualify($lead, $otherAgent);
        $this->seedConversionMasters();

        $this->actingAs($agent)
            ->post("/leads/{$lead->id}/convert", $this->conversionPayload())
            ->assertForbidden();

        $this->assertNull($lead->fresh()->converted_at);
    }
}
