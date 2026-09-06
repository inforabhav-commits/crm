<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\JustCallUserMapping;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WebhookInboxEntry;
use App\Services\Integrations\JustCall\JustCallWebhookInboxProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IncomingCallScreenPopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-16 14:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function userWithRole(string $roleSlug, array $permissions = ['calls.initiate']): User
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

    private function map(User $user, string $externalId = 'jc-agent'): void
    {
        JustCallUserMapping::create([
            'user_id' => $user->id,
            'justcall_user_id' => $externalId,
            'active_user_id' => $user->id,
            'active_justcall_user_id' => $externalId,
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

    private function lead(User $owner, string $phone = '+1 555 010 2000'): Lead
    {
        return Lead::create([
            'name' => 'Screen Lead',
            'phone' => $phone,
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
        ]);
    }

    private function customer(User $owner, string $phone = '+1 555 010 2000', string $name = 'Screen Customer'): Customer
    {
        return Customer::create([
            'name' => $name,
            'phone' => $phone,
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);
    }

    private function contact(Customer $customer, string $phone = '+1 555 010 2000', string $firstName = 'Screen'): Contact
    {
        return Contact::create([
            'customer_id' => $customer->id,
            'first_name' => $firstName,
            'last_name' => 'Contact',
            'phone' => $phone,
            'is_active' => true,
        ]);
    }

    private function processInbound(array $overrides = []): void
    {
        $payload = array_replace_recursive([
            'request_id' => 'evt-'.uniqid(),
            'type' => 'incoming.call',
            'data' => [
                'call_id' => 'incoming-1',
                'agent_id' => 'jc-agent',
                'from_number' => '+1 555 010 2000',
                'to_number' => '+1 555 010 9000',
            ],
        ], $overrides);

        $entry = WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => $payload['type'],
            'external_id' => $payload['request_id'],
            'payload_hash' => hash('sha256', json_encode($payload)),
            'payload' => $payload,
            'received_at' => now(),
            'processing_status' => 'pending',
        ]);

        app(JustCallWebhookInboxProcessor::class)->process($entry);
    }

    public function test_mapped_agent_receives_inbound_screen_pop()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);

        $this->processInbound();

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertOk()
            ->assertJsonPath('screen_pop.status', 'ringing')
            ->assertJsonPath('screen_pop.masked_number', 'XXXXXXX2000');
    }

    public function test_other_agent_does_not_receive_it()
    {
        $agent = $this->userWithRole('agent');
        $other = $this->userWithRole('agent');
        $this->map($agent);

        $this->processInbound();

        $this->actingAs($other)
            ->getJson(route('screen-pop.current'))
            ->assertOk()
            ->assertJsonPath('screen_pop', null);
    }

    public function test_contact_match_shown()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $customer = $this->customer($agent, '+1 555 010 3000');
        $contact = $this->contact($customer);

        $this->processInbound();

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertJsonPath('screen_pop.match_state', 'matched')
            ->assertJsonPath('screen_pop.record.type', 'Contact')
            ->assertJsonPath('screen_pop.record.name', $customer->name)
            ->assertJsonPath('screen_pop.record.open_label', 'Open Customer');
    }

    public function test_customer_match_shown()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $customer = $this->customer($agent);

        $this->processInbound();

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertJsonPath('screen_pop.match_state', 'matched')
            ->assertJsonPath('screen_pop.record.type', 'Customer')
            ->assertJsonPath('screen_pop.record.name', $customer->name);
    }

    public function test_lead_match_shown()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $lead = $this->lead($agent);

        $this->processInbound();

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertJsonPath('screen_pop.match_state', 'matched')
            ->assertJsonPath('screen_pop.record.type', 'Lead')
            ->assertJsonPath('screen_pop.record.name', $lead->name);
    }

    public function test_unknown_caller_handled()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);

        $this->processInbound();

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertJsonPath('screen_pop.match_state', 'unknown')
            ->assertJsonPath('screen_pop.record', null);
    }

    public function test_ambiguous_match_handled_safely()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $this->contact($this->customer($agent, '+1 555 010 3000'), '+1 555 010 2000', 'First');
        $this->contact($this->customer($agent, '+1 555 010 3001'), '+1 555 010 2000', 'Second');

        $this->processInbound();

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertJsonPath('screen_pop.match_state', 'ambiguous')
            ->assertJsonPath('screen_pop.record', null)
            ->assertJsonPath('screen_pop.match_count', 2);
    }

    public function test_duplicate_webhook_does_not_create_duplicate_popup()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);

        $this->processInbound(['request_id' => 'evt-one', 'data' => ['call_id' => 'same-call']]);
        $this->processInbound(['request_id' => 'evt-two', 'data' => ['call_id' => 'same-call']]);

        $this->assertDatabaseCount('call_logs', 1);
        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertOk()
            ->assertJsonPath('screen_pop.status', 'ringing');
    }

    public function test_missed_call_remains_masked_until_expiry()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);

        $this->processInbound(['request_id' => 'evt-ring', 'data' => ['call_id' => 'ending-call']]);
        $this->processInbound([
            'request_id' => 'evt-missed',
            'type' => 'call.missed',
            'data' => [
                'call_id' => 'ending-call',
                'agent_id' => 'jc-agent',
                'direction' => 'incoming',
                'from_number' => '+1 555 010 2000',
            ],
        ]);

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertOk()
            ->assertJsonPath('screen_pop.status', 'missed')
            ->assertJsonPath('screen_pop.masked_number', 'XXXXXXX2000')
            ->assertDontSee('+1 555 010 2000', false);
        $this->travel(6)->minutes();
        $this->getJson(route('screen-pop.current'))->assertJsonPath('screen_pop', null);
    }

    public function test_unauthorized_record_details_are_not_exposed()
    {
        $agent = $this->userWithRole('agent');
        $other = $this->userWithRole('agent');
        $this->map($agent);
        $lead = $this->lead($other);

        $this->processInbound();

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertOk()
            ->assertJsonPath('screen_pop.match_state', 'restricted')
            ->assertJsonPath('screen_pop.record', null)
            ->assertDontSee($lead->name);
    }

    public function test_dismissed_popup_is_not_returned_again()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);

        $this->processInbound();
        $callLog = \App\Models\CallLog::firstOrFail();

        $this->actingAs($agent)
            ->postJson(route('screen-pop.dismiss', $callLog))
            ->assertOk()
            ->assertJson(['dismissed' => true]);

        $this->actingAs($agent)
            ->getJson(route('screen-pop.current'))
            ->assertJsonPath('screen_pop', null);
    }

    public function test_completed_and_failed_webhooks_return_masked_payloads(): void
    {
        $agent = $this->userWithRole('agent', ['calls.initiate', 'calls.view', 'customers.view']);
        $this->map($agent);
        $this->customer($agent);
        foreach (['completed', 'failed'] as $status) {
            $this->processInbound(['type' => 'call.'.$status, 'data' => [
                'call_id' => 'terminal-'.$status, 'direction' => 'incoming',
                'notes' => 'Call +1 555 010 2000 back',
            ]]);
            $response = $this->actingAs($agent)->getJson(route('screen-pop.current'))->assertOk()
                ->assertJsonPath('screen_pop.status', $status)
                ->assertJsonPath('screen_pop.masked_number', 'XXXXXXX2000');
            $this->assertStringNotContainsString('+1 555 010 2000', $response->getContent());
            $this->assertStringNotContainsString('15550102000', $response->getContent());
            $this->assertArrayNotHasKey('caller_phone', $response->json('screen_pop'));
            $this->postJson($response->json('screen_pop.dismiss_url'))->assertOk();
        }
    }
}
