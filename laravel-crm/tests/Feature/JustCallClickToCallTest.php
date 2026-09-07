<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\JustCallUserMapping;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JustCallClickToCallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('justcall.enabled', true);
        Config::set('justcall.dialer_url', 'https://app.justcall.test/dialer');
        Config::set('justcall.api_key', 'secret-api-key');
        Config::set('justcall.api_secret', 'secret-api-secret');
        Config::set('justcall.webhook_secret', 'secret-webhook-secret');
    }

    private function userWithRole(string $roleSlug, array $permissions = [], array $overrides = []): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => str($roleSlug)->replace('-', ' ')->title()->toString()]);
        foreach ($permissions as $permission) {
            $model = Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]);
            $role->permissions()->syncWithoutDetaching($model->id);
        }

        $user = User::factory()->create(array_merge([
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $overrides));
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function map(User $user, bool $active = true): JustCallUserMapping
    {
        return JustCallUserMapping::create([
            'user_id' => $user->id,
            'justcall_user_id' => 'jc-'.$user->id,
            'active_user_id' => $active ? $user->id : null,
            'active_justcall_user_id' => $active ? 'jc-'.$user->id : null,
            'is_active' => $active,
        ]);
    }

    private function lead(User $owner, ?string $phone = '+1 555 010 2000'): Lead
    {
        return Lead::create([
            'name' => 'Callable Lead',
            'phone' => $phone,
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
        ]);
    }

    private function customer(User $owner, ?string $phone = '+1 555 010 3000'): Customer
    {
        return Customer::create([
            'name' => 'Callable Customer',
            'phone' => $phone,
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);
    }

    private function contact(Customer $customer, ?string $phone = '+1 555 010 4000'): Contact
    {
        return Contact::create([
            'customer_id' => $customer->id,
            'first_name' => 'Callable',
            'last_name' => 'Contact',
            'phone' => $phone,
            'is_active' => true,
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

    public function test_mapped_authorized_manager_retains_existing_call_flow()
    {
        $agent = $this->userWithRole('manager', ['customers.view_full_phone', 'calls.initiate', 'leads.view']);
        $this->map($agent);
        $lead = $this->lead($agent);

        $response = $this->actingAs($agent)
            ->post(route('leads.justcall.call', $lead))
            ->assertRedirect();

        $this->assertStringStartsWith('https://app.justcall.test/dialer?', $response->headers->get('Location'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'justcall.click_to_call_requested',
            'entity_type' => Lead::class,
            'entity_id' => $lead->id,
            'user_id' => $agent->id,
        ]);
    }

    public function test_admin_retains_browser_dialer_payload()
    {
        $admin = $this->userWithRole('admin', ['calls.initiate', 'customers.view']);
        $this->map($admin);
        $customer = $this->customer($admin);

        $this->actingAs($admin)->postJson(route('customers.justcall.call', $customer))
            ->assertOk()->assertJsonPath('number', '+15550103000')
            ->assertJsonPath('ok', true);
    }

    public function test_restricted_call_fails_closed_for_every_record_type_even_with_api_credentials()
    {
        Http::fake();
        $agent = $this->userWithRole('agent', ['calls.initiate', 'customers.view', 'leads.view', 'contacts.view']);
        $this->map($agent);
        $customer = $this->customer($agent);
        $customer->update(['name' => 'Customer +1 555 010 3000']);

        foreach (['customers' => $customer, 'leads' => $this->lead($agent), 'contacts' => $this->contact($customer)] as $type => $record) {
            $response = $this->actingAs($agent)->postJson(route($type.'.justcall.call', $record))
                ->assertStatus(422)->assertJsonPath('ok', false)
                ->assertJsonPath('code', 'secure_calling_not_supported')
                ->assertJsonPath('direction', 'outbound')->assertJsonPath('status', 'failed');
            $this->assertSame(['ok', 'message', 'code', 'masked_number', 'record', 'direction', 'status'], array_keys($response->json()));
            $this->assertStringNotContainsString($record->phone, $response->getContent());
            $this->assertStringNotContainsString(preg_replace('/\D/', '', $record->phone), $response->getContent());
            $this->actingAs($agent)->get(route($type.'.show', $record))->assertOk()
                ->assertDontSee($record->phone, false)
                ->assertDontSee('id="justcall-dialer"', false)
                ->assertSee('id="crm-private-dialer"', false);
        }

        Http::assertNothingSent();
    }

    public function test_restricted_non_json_request_never_redirects_to_provider()
    {
        $agent = $this->userWithRole('agent', ['calls.initiate', 'customers.view']);
        $this->map($agent);
        $customer = $this->customer($agent);
        $this->actingAs($agent)->from(route('customers.show', $customer))
            ->post(route('customers.justcall.call', $customer))
            ->assertRedirect(route('customers.show', $customer))->assertSessionHas('error');
        $this->assertStringNotContainsString($customer->phone, session('error'));
    }

    public function test_inactive_user_fails_safely_at_service_boundary()
    {
        Http::fake();
        $agent = $this->userWithRole('agent', [], ['is_active' => false]);
        $this->map($agent);
        $result = app(\App\Services\Integrations\JustCall\JustCallClickToCallService::class)
            ->launch($agent, $this->customer($agent), \Illuminate\Http\Request::create('/'));
        $this->assertSame(['ok' => false, 'message' => 'Your CRM account is inactive.'], $result);
        Http::assertNothingSent();
    }

    public function test_lead_click_to_call()
    {
        $agent = $this->userWithRole('manager', ['customers.view_full_phone', 'calls.initiate', 'leads.view']);
        $this->map($agent);
        $lead = $this->lead($agent);

        $response = $this->actingAs($agent)
            ->post(route('leads.justcall.call', $lead))
            ->assertRedirect();

        $this->assertStringContainsString('numbers=%2B15550102000', $response->headers->get('Location'));
    }

    public function test_authorized_json_request_returns_embedded_dialer_payload()
    {
        $agent = $this->userWithRole('manager', ['customers.view_full_phone', 'calls.initiate', 'leads.view']);
        $this->map($agent);
        $lead = $this->lead($agent);

        $this->actingAs($agent)
            ->postJson(route('leads.justcall.call', $lead))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('number', '+15550102000')
            ->assertJsonPath('display_phone', '+1 555 010 2000')
            ->assertJsonPath('record.type', 'Lead')
            ->assertJsonPath('record.id', $lead->id);
    }

    public function test_customer_click_to_call()
    {
        $agent = $this->userWithRole('manager', ['customers.view_full_phone', 'calls.initiate', 'customers.view']);
        $this->map($agent);
        $customer = $this->customer($agent);

        $response = $this->actingAs($agent)
            ->post(route('customers.justcall.call', $customer))
            ->assertRedirect();

        $this->assertStringContainsString('numbers=%2B15550103000', $response->headers->get('Location'));
    }

    public function test_contact_click_to_call()
    {
        $agent = $this->userWithRole('manager', ['customers.view_full_phone', 'calls.initiate', 'contacts.view', 'customers.view']);
        $this->map($agent);
        $customer = $this->customer($agent);
        $contact = $this->contact($customer);

        $response = $this->actingAs($agent)
            ->post(route('contacts.justcall.call', $contact))
            ->assertRedirect();

        $this->assertStringContainsString('numbers=%2B15550104000', $response->headers->get('Location'));
    }

    public function test_unmapped_crm_user_is_blocked()
    {
        $agent = $this->userWithRole('agent', ['calls.initiate', 'leads.view']);
        $lead = $this->lead($agent);

        $this->actingAs($agent)
            ->from(route('leads.show', $lead))
            ->post(route('leads.justcall.call', $lead))
            ->assertRedirect(route('leads.show', $lead))
            ->assertSessionHas('error', 'Your CRM user is not mapped to an active JustCall user.');
    }

    public function test_inactive_mapping_blocked()
    {
        $agent = $this->userWithRole('agent', ['calls.initiate', 'leads.view']);
        $this->map($agent, false);
        $lead = $this->lead($agent);

        $this->actingAs($agent)
            ->from(route('leads.show', $lead))
            ->post(route('leads.justcall.call', $lead))
            ->assertRedirect(route('leads.show', $lead))
            ->assertSessionHas('error', 'Your CRM user is not mapped to an active JustCall user.');
    }

    public function test_missing_or_invalid_phone_handled()
    {
        $agent = $this->userWithRole('agent', ['calls.initiate', 'leads.view']);
        $this->map($agent);
        $lead = $this->lead($agent, '123');

        $this->actingAs($agent)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertDontSee(route('leads.justcall.call', $lead), false);

        $this->actingAs($agent)
            ->from(route('leads.show', $lead))
            ->post(route('leads.justcall.call', $lead))
            ->assertRedirect(route('leads.show', $lead))
            ->assertSessionHas('error', 'This record does not have a valid phone number to call.');
    }

    public function test_unauthorized_record_call_blocked()
    {
        $agent = $this->userWithRole('agent', ['calls.initiate', 'leads.view']);
        $other = $this->userWithRole('agent');
        $this->map($agent);
        $lead = $this->lead($other);

        $this->actingAs($agent)
            ->post(route('leads.justcall.call', $lead))
            ->assertForbidden();
    }

    public function test_justcall_disabled_state_handled()
    {
        Config::set('justcall.enabled', false);
        $agent = $this->userWithRole('agent', ['calls.initiate', 'leads.view']);
        $this->map($agent);
        $lead = $this->lead($agent);

        $this->actingAs($agent)
            ->from(route('leads.show', $lead))
            ->post(route('leads.justcall.call', $lead))
            ->assertRedirect(route('leads.show', $lead))
            ->assertSessionHas('error', 'JustCall integration is disabled.');
    }

    public function test_secrets_are_not_exposed_to_rendered_page()
    {
        $agent = $this->userWithRole('agent', ['calls.initiate', 'leads.view']);
        $this->map($agent);
        $lead = $this->lead($agent);

        $this->actingAs($agent)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee(route('leads.justcall.call', $lead), false)
            ->assertDontSee('target="_blank"', false)
            ->assertDontSee('secret-api-key')
            ->assertDontSee('secret-api-secret')
            ->assertDontSee('secret-webhook-secret');
    }

    public function test_audit_log_masks_target_phone_and_contains_no_secrets()
    {
        $agent = $this->userWithRole('manager', ['customers.view_full_phone', 'calls.initiate', 'leads.view']);
        $this->map($agent);
        $lead = $this->lead($agent);

        $this->actingAs($agent)->post(route('leads.justcall.call', $lead));

        $audit = AuditLog::where('action', 'justcall.click_to_call_requested')->firstOrFail();
        $encoded = json_encode($audit->toArray());

        $this->assertSame('*******2000', $audit->new_values['target_phone']);
        $this->assertStringNotContainsString('secret-api-key', $encoded);
        $this->assertStringNotContainsString('secret-api-secret', $encoded);
        $this->assertStringNotContainsString('secret-webhook-secret', $encoded);
    }
}
