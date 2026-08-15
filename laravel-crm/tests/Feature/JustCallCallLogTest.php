<?php

namespace Tests\Feature;

use App\Models\CallLog;
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

class JustCallCallLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-16 11:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    private function lead(string $phone, User $owner): Lead
    {
        return Lead::create([
            'name' => 'Lead Person',
            'phone' => $phone,
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ]);
    }

    private function customer(string $phone, User $owner): Customer
    {
        return Customer::create([
            'name' => 'Customer Account',
            'phone' => $phone,
            'owner_id' => $owner->id,
            'is_active' => true,
            'created_by_id' => $owner->id,
            'updated_by_id' => $owner->id,
        ]);
    }

    private function contact(Customer $customer, string $phone): Contact
    {
        return Contact::create([
            'customer_id' => $customer->id,
            'first_name' => 'Contact',
            'last_name' => 'Person',
            'phone' => $phone,
            'is_active' => true,
        ]);
    }

    private function process(array $payload): WebhookInboxEntry
    {
        $entry = WebhookInboxEntry::create([
            'provider' => 'justcall',
            'event_type' => $payload['type'] ?? null,
            'external_id' => $payload['request_id'] ?? null,
            'payload_hash' => hash('sha256', json_encode($payload)),
            'payload' => $payload,
            'received_at' => now(),
            'processing_status' => 'pending',
        ]);

        return app(JustCallWebhookInboxProcessor::class)->process($entry);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'request_id' => 'evt-1',
            'type' => 'call.completed',
            'data' => [
                'call_id' => 'call-1',
                'direction' => 'outbound',
                'status' => 'completed',
                'agent_id' => 'jc-agent',
                'from_number' => '+1 555 010 1000',
                'to_number' => '+1 555 010 2000',
                'started_at' => '2026-08-16 10:55:00',
                'ended_at' => '2026-08-16 10:59:00',
                'duration_seconds' => 240,
            ],
        ], $overrides);
    }

    public function test_outbound_call_creates_call_log()
    {
        $this->process($this->payload());

        $this->assertDatabaseHas('call_logs', [
            'provider' => 'justcall',
            'external_call_id' => 'call-1',
            'direction' => 'outbound',
            'status' => 'completed',
            'customer_number_normalized' => '15550102000',
        ]);
    }

    public function test_inbound_call_creates_call_log()
    {
        $this->process($this->payload([
            'request_id' => 'evt-inbound',
            'type' => 'incoming.call',
            'data' => [
                'call_id' => 'call-inbound',
                'direction' => 'incoming',
                'from_number' => '+1 555 010 3000',
                'to_number' => '+1 555 010 1000',
            ],
        ]));

        $callLog = CallLog::firstOrFail();
        $this->assertSame('inbound', $callLog->direction);
        $this->assertSame('15550103000', $callLog->customer_number_normalized);
    }

    public function test_answered_and_completed_events_update_same_call()
    {
        $this->process($this->payload([
            'request_id' => 'evt-answer',
            'type' => 'call.answered',
            'data' => [
                'call_id' => 'call-shared',
                'direction' => 'outbound',
                'answered_at' => '2026-08-16 10:56:00',
            ],
        ]));
        $this->process($this->payload([
            'request_id' => 'evt-complete',
            'data' => [
                'call_id' => 'call-shared',
                'duration_seconds' => 180,
            ],
        ]));

        $this->assertDatabaseCount('call_logs', 1);
        $callLog = CallLog::firstOrFail();
        $this->assertSame('completed', $callLog->status);
        $this->assertSame(240, $callLog->duration_seconds);
        $this->assertNotNull($callLog->answered_at);
    }

    public function test_duplicate_event_does_not_duplicate_call()
    {
        $payload = $this->payload(['request_id' => 'evt-duplicate']);

        $this->process($payload);
        $this->process(array_replace($payload, ['request_id' => 'evt-duplicate-copy']));

        $this->assertDatabaseCount('call_logs', 1);
    }

    public function test_out_of_order_events_do_not_corrupt_better_data()
    {
        $this->process($this->payload([
            'request_id' => 'evt-completed-first',
            'type' => 'call.completed',
            'data' => [
                'call_id' => 'call-order',
                'status' => 'completed',
                'ended_at' => '2026-08-16 10:59:00',
                'duration_seconds' => 240,
            ],
        ]));
        $this->process($this->payload([
            'request_id' => 'evt-initiated-late',
            'type' => 'call.initiated',
            'data' => [
                'call_id' => 'call-order',
                'status' => 'initiated',
                'started_at' => '2026-08-16 10:55:00',
            ],
        ]));

        $callLog = CallLog::firstOrFail();
        $this->assertSame('completed', $callLog->status);
        $this->assertSame(240, $callLog->duration_seconds);
        $this->assertNotNull($callLog->started_at);
        $this->assertNotNull($callLog->ended_at);
    }

    public function test_crm_agent_mapping_works()
    {
        $agent = $this->userWithRole('agent');
        JustCallUserMapping::create([
            'user_id' => $agent->id,
            'justcall_user_id' => 'jc-agent',
            'active_user_id' => $agent->id,
            'active_justcall_user_id' => 'jc-agent',
            'is_active' => true,
        ]);

        $this->process($this->payload());

        $this->assertSame($agent->id, CallLog::firstOrFail()->user_id);
    }

    public function test_contact_phone_matching_works()
    {
        $owner = $this->userWithRole('agent');
        $customer = $this->customer('+1 555 010 9999', $owner);
        $contact = $this->contact($customer, '+1 (555) 010-2000');

        $this->process($this->payload());

        $callLog = CallLog::firstOrFail();
        $this->assertSame($contact->id, $callLog->contact_id);
        $this->assertSame($customer->id, $callLog->customer_id);
    }

    public function test_customer_phone_matching_works()
    {
        $owner = $this->userWithRole('agent');
        $customer = $this->customer('+1 (555) 010-2000', $owner);

        $this->process($this->payload());

        $this->assertSame($customer->id, CallLog::firstOrFail()->customer_id);
    }

    public function test_lead_phone_matching_works()
    {
        $owner = $this->userWithRole('agent');
        $lead = $this->lead('+1 555 010 2000', $owner);

        $this->process($this->payload());

        $this->assertSame($lead->id, CallLog::firstOrFail()->lead_id);
    }

    public function test_ambiguous_or_unmatched_call_is_preserved_safely()
    {
        $owner = $this->userWithRole('agent');
        $this->contact($this->customer('+1 555 010 9000', $owner), '+1 555 010 2000');
        $this->contact($this->customer('+1 555 010 9001', $owner), '+1 555 010 2000');

        $this->process($this->payload());

        $callLog = CallLog::firstOrFail();
        $this->assertNull($callLog->contact_id);
        $this->assertNull($callLog->customer_id);
        $this->assertSame('15550102000', $callLog->customer_number_normalized);
    }

    public function test_call_appears_in_correct_crm_timeline()
    {
        $owner = $this->userWithRole('agent', ['calls.view', 'leads.view']);
        $lead = $this->lead('+1 555 010 2000', $owner);
        $this->process($this->payload());

        $this->actingAs($owner)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('Call History')
            ->assertSee('Outbound')
            ->assertSee('Completed');
    }

    public function test_agent_cannot_access_unauthorized_call()
    {
        $agent = $this->userWithRole('agent', ['calls.view']);
        $other = $this->userWithRole('agent');
        $customer = $this->customer('+1 555 010 9000', $other);
        $this->contact($customer, '+1 555 010 2000');
        JustCallUserMapping::create([
            'user_id' => $agent->id,
            'justcall_user_id' => 'jc-agent',
            'active_user_id' => $agent->id,
            'active_justcall_user_id' => 'jc-agent',
            'is_active' => true,
        ]);
        $this->process($this->payload());

        $this->actingAs($agent)
            ->get(route('calls.index'))
            ->assertOk()
            ->assertDontSee('Customer Account')
            ->assertDontSee('+1 555 010 2000');
    }
}
