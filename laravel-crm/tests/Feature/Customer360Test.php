<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Customer360Test extends TestCase
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

    private function leadStatus(): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => 'lead_status',
            'slug' => 'new',
        ], [
            'name' => 'New',
            'is_active' => true,
        ]);
    }

    private function customerFor(User $owner, array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => '360 Customer',
            'company' => '360 Co',
            'email' => 'customer@example.com',
            'phone' => '5551001000',
            'owner_id' => $owner->id,
            'is_active' => true,
            'notes' => 'Customer history note.',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    private function contactFor(Customer $customer, array $overrides = []): Contact
    {
        return Contact::create(array_merge([
            'customer_id' => $customer->id,
            'first_name' => 'Primary',
            'last_name' => 'Contact',
            'email' => 'primary@example.com',
            'phone' => '5552002000',
            'is_primary' => true,
            'is_active' => true,
            'created_by_id' => $customer->owner_id,
            'updated_by_id' => $customer->owner_id,
        ], $overrides));
    }

    private function activityFor(User $owner, object $related, array $overrides = []): Activity
    {
        return Activity::create(array_merge([
            'activity_type_id' => $this->activityType()->id,
            'subject' => 'Timeline activity',
            'related_type' => get_class($related),
            'related_id' => $related->id,
            'assigned_user_id' => $owner->id,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ], $overrides));
    }

    public function test_customer_360_page_loads()
    {
        $agent = $this->userWithRole('agent', ['customers.view']);
        $lead = Lead::create([
            'name' => 'Source Lead',
            'lead_status_id' => $this->leadStatus()->id,
            'owner_id' => $agent->id,
            'priority' => 'normal',
            'created_by_id' => $agent->id,
            'updated_by_id' => $agent->id,
        ]);
        $customer = $this->customerFor($agent, ['converted_from_lead_id' => $lead->id]);

        $this->actingAs($agent)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Customer 360')
            ->assertSee('360 Customer')
            ->assertSee('Source Lead');
    }

    public function test_related_contacts_appear()
    {
        $agent = $this->userWithRole('agent', ['customers.view']);
        $customer = $this->customerFor($agent);
        $this->contactFor($customer, ['first_name' => 'Primary', 'last_name' => 'Buyer']);
        $this->contactFor($customer, ['first_name' => 'Secondary', 'last_name' => 'Buyer', 'is_primary' => false]);

        $this->actingAs($agent)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Primary Buyer')
            ->assertSee('Secondary Buyer');
    }

    public function test_activities_appear_chronologically()
    {
        $agent = $this->userWithRole('agent', ['customers.view', 'activities.view']);
        $customer = $this->customerFor($agent);
        $contact = $this->contactFor($customer);

        $this->activityFor($agent, $customer, [
            'subject' => 'Older customer note',
            'due_at' => now()->subDays(2),
        ]);
        $this->activityFor($agent, $contact, [
            'subject' => 'Newer contact note',
            'due_at' => now()->subDay(),
        ]);

        $this->actingAs($agent)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSeeInOrder(['Newer contact note', 'Older customer note']);
    }

    public function test_upcoming_and_overdue_items_are_correct()
    {
        $agent = $this->userWithRole('agent', ['customers.view', 'activities.view']);
        $customer = $this->customerFor($agent);

        $this->activityFor($agent, $customer, [
            'subject' => 'Overdue follow-up',
            'due_at' => now()->subDay(),
            'status' => 'pending',
        ]);
        $this->activityFor($agent, $customer, [
            'subject' => 'Upcoming follow-up',
            'due_at' => now()->addDay(),
            'status' => 'pending',
        ]);
        $this->activityFor($agent, $customer, [
            'subject' => 'Completed old call',
            'due_at' => now()->subDays(3),
            'status' => 'completed',
            'completed_at' => now()->subDays(2),
        ]);

        $this->actingAs($agent)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSeeInOrder(['Upcoming Follow-ups', 'Upcoming follow-up'])
            ->assertSeeInOrder(['Overdue Follow-ups', 'Overdue follow-up'])
            ->assertSee('Completed old call');
    }

    public function test_unauthorized_customer_access_is_blocked()
    {
        $agent = $this->userWithRole('agent', ['customers.view']);
        $otherAgent = $this->userWithRole('agent', ['customers.view']);
        $customer = $this->customerFor($otherAgent);

        $this->actingAs($agent)
            ->get("/customers/{$customer->id}")
            ->assertForbidden();
    }

    public function test_agent_visibility_remains_correct()
    {
        $agent = $this->userWithRole('agent', ['customers.view']);
        $otherAgent = $this->userWithRole('agent', ['customers.view']);
        $ownCustomer = $this->customerFor($agent, ['name' => 'Visible 360 Customer']);
        $otherCustomer = $this->customerFor($otherAgent, ['name' => 'Hidden 360 Customer']);

        $this->actingAs($agent)
            ->get('/customers')
            ->assertOk()
            ->assertSee($ownCustomer->name)
            ->assertDontSee($otherCustomer->name);
    }
}
