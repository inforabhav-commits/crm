<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
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

class MissedCallAutomationTest extends TestCase
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

    private function lead(User $owner, string $phone = '+1 555 010 2000', string $name = 'Missed Lead'): Lead
    {
        return Lead::create([
            'name' => $name,
            'phone' => $phone,
            'lead_status_id' => $this->masterValue('lead_status', 'New')->id,
            'owner_id' => $owner->id,
            'priority' => 'normal',
        ]);
    }

    private function customer(User $owner, string $phone = '+1 555 010 2000', string $name = 'Missed Customer'): Customer
    {
        return Customer::create([
            'name' => $name,
            'phone' => $phone,
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);
    }

    private function contact(Customer $customer, string $phone = '+1 555 010 2000', string $firstName = 'Missed'): Contact
    {
        return Contact::create([
            'customer_id' => $customer->id,
            'first_name' => $firstName,
            'last_name' => 'Contact',
            'phone' => $phone,
            'is_active' => true,
        ]);
    }

    private function processEvent(array $overrides = []): WebhookInboxEntry
    {
        $payload = array_replace_recursive([
            'request_id' => 'evt-'.uniqid(),
            'type' => 'call.missed',
            'data' => [
                'call_id' => 'missed-1',
                'agent_id' => 'jc-agent',
                'direction' => 'incoming',
                'from_number' => '+1 555 010 2000',
                'to_number' => '+1 555 010 9000',
                'started_at' => now()->subMinutes(3)->toIso8601String(),
                'ended_at' => now()->subMinute()->toIso8601String(),
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

        return app(JustCallWebhookInboxProcessor::class)->process($entry);
    }

    public function test_inbound_missed_call_creates_follow_up_for_matched_lead()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $lead = $this->lead($agent);

        $this->processEvent();

        $callLog = CallLog::firstOrFail();
        $activity = Activity::firstOrFail();

        $this->assertSame('created', $callLog->missed_call_automation_status);
        $this->assertSame($activity->id, $callLog->missed_call_activity_id);
        $this->assertSame(Lead::class, $activity->related_type);
        $this->assertSame($lead->id, $activity->related_id);
        $this->assertSame($agent->id, $activity->assigned_user_id);
        $this->assertSame('Missed Call', $activity->subject);
        $this->assertTrue($activity->due_at->equalTo(now()->addHour()->startOfMinute()));
    }

    public function test_notification_is_created_for_responsible_agent()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $this->lead($agent);

        $this->processEvent();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame('missed_call_follow_up', $agent->notifications()->first()->data['category']);
    }

    public function test_duplicate_event_does_not_duplicate_follow_up_or_notification()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $this->lead($agent);

        $this->processEvent(['request_id' => 'evt-one', 'data' => ['call_id' => 'same-missed-call']]);
        $this->processEvent(['request_id' => 'evt-two', 'data' => ['call_id' => 'same-missed-call']]);

        $this->assertDatabaseCount('call_logs', 1);
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_outbound_missed_call_does_not_trigger_automation()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $this->lead($agent);

        $this->processEvent(['data' => ['direction' => 'outgoing']]);

        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_ambiguous_caller_is_preserved_without_follow_up()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $this->contact($this->customer($agent, '+1 555 010 3000'), '+1 555 010 2000', 'First');
        $this->contact($this->customer($agent, '+1 555 010 3001'), '+1 555 010 2000', 'Second');

        $this->processEvent();

        $this->assertDatabaseCount('call_logs', 1);
        $this->assertDatabaseCount('activities', 0);
        $this->assertSame('skipped_ambiguous', CallLog::first()->missed_call_automation_status);
    }

    public function test_unknown_caller_is_preserved_without_follow_up()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);

        $this->processEvent();

        $this->assertDatabaseCount('call_logs', 1);
        $this->assertDatabaseCount('activities', 0);
        $this->assertSame('skipped_unknown', CallLog::first()->missed_call_automation_status);
    }

    public function test_unmapped_agent_is_preserved_without_assignment()
    {
        $agent = $this->userWithRole('agent');
        $this->lead($agent);

        $this->processEvent();

        $this->assertDatabaseCount('call_logs', 1);
        $this->assertDatabaseCount('activities', 0);
        $this->assertSame('skipped_unmapped', CallLog::first()->missed_call_automation_status);
    }

    public function test_later_completed_event_resolves_false_missed_automation()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $this->lead($agent);

        $this->processEvent(['request_id' => 'evt-missed', 'data' => ['call_id' => 'later-completed']]);
        $this->processEvent([
            'request_id' => 'evt-completed',
            'type' => 'call.completed',
            'data' => [
                'call_id' => 'later-completed',
                'agent_id' => 'jc-agent',
                'direction' => 'incoming',
                'from_number' => '+1 555 010 2000',
                'status' => 'completed',
                'answered_at' => now()->subMinutes(2)->toIso8601String(),
                'ended_at' => now()->toIso8601String(),
            ],
        ]);

        $callLog = CallLog::firstOrFail();
        $activity = Activity::firstOrFail();

        $this->assertSame('completed', $callLog->status);
        $this->assertSame('resolved_answered', $callLog->missed_call_automation_status);
        $this->assertSame('cancelled', $activity->refresh()->status);
    }

    public function test_completed_event_before_missed_prevents_false_automation()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $this->lead($agent);

        $this->processEvent([
            'request_id' => 'evt-completed',
            'type' => 'call.completed',
            'data' => [
                'call_id' => 'completed-first',
                'agent_id' => 'jc-agent',
                'direction' => 'incoming',
                'from_number' => '+1 555 010 2000',
                'status' => 'completed',
                'answered_at' => now()->subMinutes(2)->toIso8601String(),
                'ended_at' => now()->toIso8601String(),
            ],
        ]);
        $this->processEvent(['request_id' => 'evt-missed', 'data' => ['call_id' => 'completed-first']]);

        $this->assertDatabaseCount('activities', 0);
        $this->assertSame('completed', CallLog::first()->status);
        $this->assertSame('resolved_answered', CallLog::first()->missed_call_automation_status);
    }

    public function test_unauthorized_matched_record_details_are_not_exposed()
    {
        $agent = $this->userWithRole('agent');
        $other = $this->userWithRole('agent');
        $this->map($agent);
        $lead = $this->lead($other, '+1 555 010 2000', 'Private Lead Name');

        $this->processEvent();

        $this->assertDatabaseCount('activities', 0);
        $this->assertSame('skipped_restricted', CallLog::first()->missed_call_automation_status);
        $this->assertStringNotContainsString($lead->name, AuditLog::query()->pluck('new_values')->toJson());
    }

    public function test_contact_match_is_linked_to_contact()
    {
        $agent = $this->userWithRole('agent');
        $this->map($agent);
        $contact = $this->contact($this->customer($agent, '+1 555 010 3000'));

        $this->processEvent();

        $activity = Activity::firstOrFail();
        $this->assertSame(Contact::class, $activity->related_type);
        $this->assertSame($contact->id, $activity->related_id);
    }
}
