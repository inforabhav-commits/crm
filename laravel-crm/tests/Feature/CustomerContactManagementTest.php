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

class CustomerContactManagementTest extends TestCase
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

    private function customerFor(User $owner, array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'Acme Account',
            'company' => 'Acme',
            'email' => 'account@example.com',
            'phone' => '5551001000',
            'owner_id' => $owner->id,
            'is_active' => true,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ], $overrides));
    }

    private function contactFor(Customer $customer, array $overrides = []): Contact
    {
        return Contact::create(array_merge([
            'customer_id' => $customer->id,
            'first_name' => 'Jane',
            'last_name' => 'Buyer',
            'email' => 'jane@example.com',
            'phone' => '5552002000',
            'is_active' => true,
            'created_by_id' => $customer->owner_id,
            'updated_by_id' => $customer->owner_id,
        ], $overrides));
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

    public function test_customer_crud()
    {
        $admin = $this->userWithRole('super-admin');
        $owner = $this->userWithRole('agent');

        $this->actingAs($admin)
            ->post('/customers', [
                'name' => 'Northwind Account',
                'company' => 'Northwind',
                'email' => 'hello@northwind.test',
                'phone' => '5553003000',
                'website' => 'https://northwind.test',
                'industry' => 'Software',
                'external_customer_id' => 'CUST-100',
                'amount' => '299.95',
                'plan' => 'Annual',
                'software' => 'Desktop Suite',
                'license_number' => 'LIC-100',
                'product_number' => 'PROD-100',
                'file_password' => 'file-secret',
                'cloud_customer' => 'Yes',
                'customer_user_id' => 'northwind-user',
                'customer_password' => 'customer-secret',
                'issue' => 'Install help',
                'sale_type' => 'New',
                'no_of_cases' => '3',
                'payment_type' => 'Visa',
                'last_4' => '4242',
                'card_type' => 'Credit',
                'end' => '2027-08-31',
                'owner_id' => $owner->id,
                'is_active' => '1',
                'notes' => 'Important account.',
            ])
            ->assertRedirect();

        $customer = Customer::where('name', 'Northwind Account')->firstOrFail();
        $this->assertSame($owner->id, $customer->owner_id);
        $this->assertSame('CUST-100', $customer->external_customer_id);
        $this->assertSame('LIC-100', $customer->license_number);
        $this->assertSame('customer-secret', $customer->customer_password);

        $this->actingAs($admin)
            ->put("/customers/{$customer->id}", [
                'name' => 'Northwind Updated',
                'company' => 'Northwind',
                'email' => 'updated@northwind.test',
                'phone' => '5553003001',
                'industry' => 'Services',
                'external_customer_id' => 'CUST-101',
                'amount' => '399.95',
                'plan' => 'Monthly',
                'software' => 'Cloud Suite',
                'license_number' => 'LIC-101',
                'product_number' => 'PROD-101',
                'file_password' => 'new-file-secret',
                'cloud_customer' => 'No',
                'customer_user_id' => 'updated-user',
                'customer_password' => 'new-customer-secret',
                'issue' => 'Renewal help',
                'sale_type' => 'Renewal',
                'no_of_cases' => '4',
                'payment_type' => 'Master Card',
                'last_4' => '2805',
                'card_type' => 'Debit',
                'end' => '2027-09-30',
                'owner_id' => $owner->id,
                'is_active' => '0',
            ])
            ->assertRedirect("/customers/{$customer->id}");

        $customer->refresh();
        $this->assertSame('Northwind Updated', $customer->name);
        $this->assertSame('CUST-101', $customer->external_customer_id);
        $this->assertSame('Cloud Suite', $customer->software);
        $this->assertSame('new-customer-secret', $customer->customer_password);
        $this->assertFalse($customer->is_active);
    }

    public function test_contact_crud_and_account_relationship()
    {
        $admin = $this->userWithRole('super-admin');
        $owner = $this->userWithRole('agent');
        $customer = $this->customerFor($owner);

        $this->actingAs($admin)
            ->post('/contacts', [
                'customer_id' => $customer->id,
                'first_name' => 'Rita',
                'last_name' => 'Contact',
                'title' => 'Director',
                'email' => 'rita@example.com',
                'phone' => '5554004000',
                'is_primary' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $contact = Contact::where('email', 'rita@example.com')->firstOrFail();
        $this->assertSame($customer->id, $contact->customer_id);
        $this->assertTrue($contact->is_primary);

        $this->actingAs($admin)
            ->put("/contacts/{$contact->id}", [
                'customer_id' => $customer->id,
                'first_name' => 'Rita',
                'last_name' => 'Updated',
                'title' => 'VP',
                'email' => 'rita.updated@example.com',
                'phone' => '5554004001',
                'is_active' => '0',
            ])
            ->assertRedirect("/contacts/{$contact->id}");

        $contact->refresh();
        $this->assertSame('Rita Updated', $contact->name);
        $this->assertFalse($contact->is_active);
    }

    public function test_agent_visibility()
    {
        $agent = $this->userWithRole('agent', ['customers.view', 'contacts.view']);
        $otherAgent = $this->userWithRole('agent', ['customers.view', 'contacts.view']);
        $ownCustomer = $this->customerFor($agent, [
            'name' => 'Own Customer',
            'external_customer_id' => 'LIST-100',
            'company' => 'List Business',
            'sale_date' => '44200',
            'amount' => '1560.83',
            'plan' => '1 year',
            'software' => 'QuickBooks',
            'license_number' => 'LIC-LIST',
            'product_number' => 'PROD-LIST',
            'cloud_customer' => '690215',
            'customer_user_id' => 'list-user',
            'issue' => 'Unable to login',
            'sale_type' => 'Renewal',
            'no_of_cases' => '1',
            'payment_type' => 'Master Card',
            'last_4' => '2805',
            'card_type' => 'Credit',
            'end' => '2027-01-01',
        ]);
        $otherCustomer = $this->customerFor($otherAgent, ['name' => 'Other Customer']);
        $this->contactFor($ownCustomer, ['first_name' => 'Own']);
        $this->contactFor($otherCustomer, ['first_name' => 'Other']);

        $this->actingAs($agent)
            ->get('/customers')
            ->assertOk()
            ->assertSee('Business Name')
            ->assertSee('Phone No')
            ->assertSee('Own Customer')
            ->assertSee('LIST-100')
            ->assertSee('List Business')
            ->assertDontSee('Other Customer');

        $this->actingAs($agent)
            ->get('/contacts')
            ->assertOk()
            ->assertSee('Own')
            ->assertDontSee('Other');
    }

    public function test_unauthorized_access_blocked()
    {
        $agent = $this->userWithRole('agent', ['customers.view', 'contacts.view']);
        $otherAgent = $this->userWithRole('agent', ['customers.view', 'contacts.view']);
        $customer = $this->customerFor($otherAgent);
        $contact = $this->contactFor($customer);

        $this->actingAs($agent)
            ->get("/customers/{$customer->id}")
            ->assertForbidden();

        $this->actingAs($agent)
            ->get("/contacts/{$contact->id}")
            ->assertForbidden();
    }

    public function test_activity_relation_works_with_customer_and_contact()
    {
        $admin = $this->userWithRole('super-admin');
        $owner = $this->userWithRole('agent');
        $customer = $this->customerFor($owner, ['name' => 'Activity Customer']);
        $contact = $this->contactFor($customer, ['first_name' => 'Activity', 'last_name' => 'Contact']);
        $type = $this->activityType('Meeting');

        Activity::create([
            'activity_type_id' => $type->id,
            'subject' => 'Customer review',
            'related_type' => Customer::class,
            'related_id' => $customer->id,
            'assigned_user_id' => $owner->id,
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);

        Activity::create([
            'activity_type_id' => $type->id,
            'subject' => 'Contact call',
            'related_type' => Contact::class,
            'related_id' => $contact->id,
            'assigned_user_id' => $owner->id,
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
            'priority' => 'normal',
            'status' => 'pending',
            'due_at' => now()->addDays(2),
        ]);

        $this->actingAs($admin)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertSee('Customer review');

        $this->actingAs($admin)
            ->get("/contacts/{$contact->id}")
            ->assertOk()
            ->assertSee('Contact call');
    }

    public function test_lead_to_customer_conversion_foundation()
    {
        $admin = $this->userWithRole('super-admin');
        $owner = $this->userWithRole('agent');
        $lead = Lead::create([
            'name' => 'Convert Lead',
            'company' => 'Converted Co',
            'email' => 'lead@example.com',
            'phone' => '5555005000',
            'lead_status_id' => $this->leadStatus()->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post('/customers', [
                'name' => 'Converted Co',
                'company' => 'Converted Co',
                'email' => 'lead@example.com',
                'phone' => '5555005000',
                'owner_id' => $owner->id,
                'converted_from_lead_id' => $lead->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'name' => 'Converted Co',
            'converted_from_lead_id' => $lead->id,
        ]);
    }

    public function test_customer_delete_requires_permission_and_removes_record()
    {
        $owner = $this->userWithRole('agent');
        $customer = $this->customerFor($owner, ['name' => 'Delete Me']);

        $this->actingAs($owner)
            ->delete("/customers/{$customer->id}")
            ->assertForbidden();

        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)
            ->delete("/customers/{$customer->id}")
            ->assertRedirect('/customers');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.deleted']);
    }
}
