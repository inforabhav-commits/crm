<?php

namespace Tests\Feature;

use App\Models\CallLog;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecureCallPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleSlug, array $permissions = []): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => str($roleSlug)->replace('-', ' ')->title()->toString()]);

        foreach ($permissions as $permission) {
            $model = Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]);
            $role->permissions()->syncWithoutDetaching($model->id);
        }

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function lead(User $owner, string $phone = '9876543210'): Lead
    {
        return Lead::create([
            'name' => 'Privacy Lead',
            'email' => 'privacy-lead@example.com',
            'phone' => $phone,
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
        ]);
    }

    private function customer(User $owner, string $phone = '9876543210'): Customer
    {
        return Customer::create([
            'name' => 'Rahul Sharma',
            'email' => 'rahul@example.com',
            'phone' => $phone,
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);
    }

    private function callLog(User $agent, Customer $customer, string $status = 'ringing'): CallLog
    {
        return CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'privacy-call-1',
            'external_event_id' => 'privacy-event-1',
            'direction' => 'inbound',
            'status' => $status,
            'user_id' => $agent->id,
            'agent_external_id' => 'jc-agent',
            'from_number' => '9876543210',
            'customer_number' => '9876543210',
            'customer_number_normalized' => '9876543210',
            'customer_id' => $customer->id,
            'screen_pop_expires_at' => now()->addMinutes(5),
            'last_event_at' => now(),
        ]);
    }

    private function masterValue(string $type, string $name): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => $type,
            'slug' => CrmMasterValue::makeSlug($name),
        ], [
            'name' => $name,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_agent_sees_masked_phone_and_full_number_is_absent_from_html()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'leads.edit', 'customers.view', 'contacts.view', 'calls.view', 'calls.initiate']);
        $lead = $this->lead($agent);
        $customer = $this->customer($agent);
        $this->callLog($agent, $customer);

        $this->actingAs($agent)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('XXXXXX3210')
            ->assertDontSee('9876543210', false);

        $this->actingAs($agent)
            ->get(route('leads.edit', $lead))
            ->assertOk()
            ->assertSee('XXXXXX3210')
            ->assertDontSee('value="9876543210"', false);

        $this->actingAs($agent)
            ->get(route('calls.index'))
            ->assertOk()
            ->assertSee('XXXXXX3210')
            ->assertDontSee('9876543210', false);
    }

    public function test_authorized_manager_can_see_full_phone()
    {
        $manager = $this->userWithRole('manager', ['leads.view', 'leads.assign', 'customers.view_full_phone']);
        $lead = $this->lead($manager);

        $this->actingAs($manager)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('9876543210')
            ->assertDontSee('XXXXXX3210');
    }

    public function test_leads_assign_does_not_grant_full_phone_access()
    {
        $manager = $this->userWithRole('manager', ['leads.view', 'leads.assign']);
        $lead = $this->lead($manager);

        $this->actingAs($manager)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('XXXXXX3210')
            ->assertDontSee('9876543210', false);
    }

    public function test_admin_can_see_full_phone()
    {
        $admin = $this->userWithRole('admin', ['leads.view']);
        $lead = $this->lead($admin);

        $this->actingAs($admin)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('9876543210')
            ->assertDontSee('XXXXXX3210');
    }

    public function test_screen_pop_masks_number_and_backend_customer_match_still_works()
    {
        $agent = $this->userWithRole('agent', ['customers.view', 'calls.initiate']);
        $customer = $this->customer($agent);
        $this->callLog($agent, $customer);

        $response = $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertOk()
            ->json('screen_pop');

        $this->assertSame('Rahul Sharma', $response['record']['name']);
        $this->assertSame('XXXXXX3210', $response['masked_number']);
        $this->assertSame('Open Customer', $response['record']['open_label']);
        $this->assertStringNotContainsString('9876543210', json_encode($response));
    }

    public function test_unknown_screen_pop_masks_number()
    {
        $agent = $this->userWithRole('agent', ['calls.initiate']);
        CallLog::create([
            'provider' => 'justcall',
            'external_call_id' => 'privacy-call-unknown',
            'direction' => 'inbound',
            'status' => 'ringing',
            'user_id' => $agent->id,
            'from_number' => '9876543210',
            'customer_number' => '9876543210',
            'customer_number_normalized' => '9876543210',
            'screen_pop_expires_at' => now()->addMinutes(5),
            'last_event_at' => now(),
        ]);

        $payload = $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertOk()
            ->json('screen_pop');

        $this->assertSame('unknown', $payload['match_state']);
        $this->assertSame('XXXXXX3210', $payload['masked_number']);
        $this->assertStringNotContainsString('9876543210', json_encode($payload));
    }

    public function test_agent_export_masks_phone_numbers()
    {
        $agent = $this->userWithRole('agent', ['customers.view', 'calls.view', 'export.crm']);
        $customer = $this->customer($agent);
        $this->callLog($agent, $customer);

        $csv = $this->actingAs($agent)
            ->get(route('import-export.export', 'calls'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('XXXXXX3210', $csv);
        $this->assertStringNotContainsString('9876543210', $csv);
    }

    public function test_full_phone_search_does_not_expose_records_to_restricted_agent()
    {
        $agent = $this->userWithRole('agent', ['leads.view']);
        $this->lead($agent);

        $this->actingAs($agent)
            ->get(route('leads.index', ['search' => '9876543210']))
            ->assertOk()
            ->assertSee('No leads found.')
            ->assertDontSee('Privacy Lead');
    }

    public function test_sidebar_uses_permissions_and_direct_restricted_urls_stay_blocked()
    {
        $agent = $this->userWithRole('agent', ['leads.view', 'customers.view', 'calls.view']);

        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Leads')
            ->assertSee('Customers')
            ->assertSee('Calls')
            ->assertDontSee('Users')
            ->assertDontSee('Teams')
            ->assertDontSee('CRM Settings')
            ->assertDontSee('Audit Log')
            ->assertDontSee('JustCall Settings')
            ->assertDontSee('Operational Health');

        $this->actingAs($agent)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($agent)->get(route('modules.placeholder', 'users'))->assertForbidden();
    }
}
