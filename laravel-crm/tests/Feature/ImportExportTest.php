<?php

namespace Tests\Feature;

use App\Models\CallLog;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'agent', array $permissions = [], array $overrides = []): User
    {
        $roleModel = Role::firstOrCreate(['slug' => $role], ['name' => $role]);
        foreach ($permissions as $permission) {
            $roleModel->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['slug' => $permission], ['name' => $permission])->id);
        }
        $user = User::factory()->create(array_merge(['password' => Hash::make('password'), 'is_active' => true], $overrides));
        $user->roles()->sync([$roleModel->id]);
        return $user;
    }

    private function value(string $type, string $name): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate(['type' => $type, 'slug' => CrmMasterValue::makeSlug($name)], ['name' => $name, 'is_active' => true]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('records.csv', $content);
    }

    public function test_authorized_lead_import_returns_row_summary_and_validates_rows(): void
    {
        $agent = $this->user('agent', ['import.leads']);
        $this->value('lead_status', 'New');
        $this->value('lead_source', 'Web');

        $response = $this->actingAs($agent)->post('/import-export', [
            'resource' => 'leads',
            'file' => $this->csv("name,email,phone,status,source\nGood Lead,good@example.com,15550001,New,Web\nBad Lead,bad@example.com,15550002,Missing,Web\n"),
        ]);

        $response->assertRedirect('/import-export')->assertSessionHas('import_summary.imported', 1)->assertSessionHas('import_summary.errors.0.row', 3);
        $this->assertDatabaseHas('leads', ['name' => 'Good Lead', 'owner_id' => $agent->id]);
        $this->assertDatabaseMissing('leads', ['name' => 'Bad Lead']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'crm.imported']);
    }

    public function test_duplicate_lead_phone_is_rejected_without_creating_duplicate(): void
    {
        $agent = $this->user('agent', ['import.leads']);
        $status = $this->value('lead_status', 'New');
        $source = $this->value('lead_source', 'Web');
        Lead::create(['name' => 'Existing', 'email' => 'existing@example.com', 'phone' => '+1 555 0003', 'lead_status_id' => $status->id, 'lead_source_id' => $source->id, 'owner_id' => $agent->id, 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id]);

        $this->actingAs($agent)->post('/import-export', ['resource' => 'leads', 'file' => $this->csv("name,email,phone,status,source\nDuplicate,new@example.com,15550003,New,Web\n")])->assertSessionHas('import_summary.imported', 0);
        $this->assertSame(1, Lead::count());
    }

    public function test_customer_and_contact_import_validate_relationship_and_duplicate(): void
    {
        $agent = $this->user('agent', ['import.customers']);
        $this->actingAs($agent)->post('/import-export', ['resource' => 'customers', 'file' => $this->csv("name,email,phone\nImported Customer,c@example.com,15550004\n")])->assertSessionHas('import_summary.imported', 1);
        $customer = Customer::where('email', 'c@example.com')->firstOrFail();

        $this->actingAs($agent)->post('/import-export', ['resource' => 'contacts', 'file' => $this->csv("first_name,last_name,email,customer_id\nJane,Smith,jane@example.com,{$customer->id}\nBad,Person,bad@example.com,9999\n")])->assertSessionHas('import_summary.imported', 1);
        $this->assertDatabaseHas('contacts', ['email' => 'jane@example.com', 'customer_id' => $customer->id]);
        $this->assertSame(1, Contact::count());
    }

    public function test_unauthorized_import_and_export_are_blocked(): void
    {
        $agent = $this->user();
        $this->actingAs($agent)->post('/import-export', ['resource' => 'leads', 'file' => $this->csv("name\nBlocked\n")])->assertForbidden();
        $this->actingAs($agent)->get('/export/leads')->assertForbidden();
    }

    public function test_export_respects_agent_visibility_filters_and_excludes_provider_data(): void
    {
        $agent = $this->user('agent', ['export.crm']);
        $other = $this->user('agent', ['export.crm']);
        $status = $this->value('lead_status', 'New');
        $source = $this->value('lead_source', 'Web');
        Lead::create(['name' => 'Visible Export', 'email' => 'visible@example.com', 'phone' => '15550005', 'lead_status_id' => $status->id, 'lead_source_id' => $source->id, 'owner_id' => $agent->id, 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id]);
        Lead::create(['name' => 'Hidden Export', 'email' => 'hidden@example.com', 'phone' => '15550006', 'lead_status_id' => $status->id, 'lead_source_id' => $source->id, 'owner_id' => $other->id, 'created_by_id' => $other->id, 'updated_by_id' => $other->id]);
        Lead::create(['name' => '=FORMULA', 'email' => 'formula@example.com', 'phone' => '15550007', 'lead_status_id' => $status->id, 'lead_source_id' => $source->id, 'owner_id' => $agent->id, 'created_by_id' => $agent->id, 'updated_by_id' => $agent->id]);
        CallLog::create(['provider' => 'justcall', 'external_call_id' => 'call-export', 'direction' => 'inbound', 'status' => 'answered', 'user_id' => $agent->id, 'customer_number' => '15550005', 'provider_notes' => 'provider-internal', 'recording_url' => 'https://secret.invalid/recording', 'last_event_at' => now()]);

        $response = $this->actingAs($agent)->get('/export/leads?status='.$status->id);
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Visible Export', $csv);
        $this->assertStringContainsString("'=FORMULA", $csv);
        $this->assertStringNotContainsString('Hidden Export', $csv);

        $callCsv = $this->actingAs($agent)->get('/export/calls')->streamedContent();
        $this->assertStringContainsString('call-export', $callCsv);
        $this->assertStringNotContainsString('provider-internal', $callCsv);
        $this->assertStringNotContainsString('recording.invalid', $callCsv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'crm.exported']);
    }

    public function test_manager_team_export_excludes_outside_team_records(): void
    {
        $manager = $this->user('manager', ['export.crm']);
        $agent = $this->user('agent', ['export.crm'], ['reports_to_id' => $manager->id]);
        $outside = $this->user('agent', ['export.crm']);
        $status = $this->value('lead_status', 'New');
        $source = $this->value('lead_source', 'Web');
        $team = Team::create(['name' => 'Team Export', 'manager_id' => $manager->id, 'is_active' => true]);
        $team->members()->attach([$manager->id, $agent->id]);
        foreach ([[$manager, 'Manager Team Record'], [$agent, 'Agent Team Record'], [$outside, 'Outside Team Record']] as [$owner, $name]) {
            Lead::create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'phone' => (string) random_int(15550010, 15550099), 'lead_status_id' => $status->id, 'lead_source_id' => $source->id, 'owner_id' => $owner->id, 'created_by_id' => $owner->id, 'updated_by_id' => $owner->id]);
        }

        $csv = $this->actingAs($manager)->get('/export/leads?team='.$team->id)->streamedContent();
        $this->assertStringContainsString('Manager Team Record', $csv);
        $this->assertStringContainsString('Agent Team Record', $csv);
        $this->assertStringNotContainsString('Outside Team Record', $csv);
    }
}
