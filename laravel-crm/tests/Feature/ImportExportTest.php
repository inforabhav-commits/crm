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
use ZipArchive;

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

    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'crm-xlsx-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Customers" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rows));
        $zip->close();

        return new UploadedFile($path, 'records.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $xml .= '<row r="'.($rowIndex + 1).'">';
            foreach ($row as $columnIndex => $value) {
                $cell = $this->columnName($columnIndex).($rowIndex + 1);
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function columnName(int $index): string
    {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + (($index - 1) % 26)).$name;
        }

        return $name;
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

    public function test_customer_csv_import_supports_ob_data_format_and_skips_sensitive_fields(): void
    {
        $agent = $this->user('agent', ['import.customers']);
        $csv = "Customer ID,Name,Email,Business Name,Phone No,Billing Address,Card No,CVV,Date,Amount,Plan,Software ,Liscense Number,Product Number,File Password,Cloud Customer,User ID,Password,Issue,Sale Type,No of Cases,Payment Type,Last 4\n".
            "C-100,CSV Customer,csv-customer@example.com,CSV Business,1555010101,123 Billing St,4111111111111111,123,44200,99.95,1 year,Quickbooks Pro,LIC-1,PROD-1,file-secret,yes,portal-user,portal-secret,Login issue,Renewal,2,Visa,4242\n";

        $this->actingAs($agent)->post('/import-export', [
            'resource' => 'customers',
            'file' => $this->csv($csv),
        ])->assertRedirect('/import-export')->assertSessionHas('import_summary.imported', 1);

        $customer = Customer::where('email', 'csv-customer@example.com')->firstOrFail();
        $this->assertSame('CSV Business', $customer->company);
        $this->assertSame('1555010101', $customer->phone);
        $this->assertSame('123 Billing St', $customer->address);
        $this->assertStringContainsString('Customer ID: C-100', $customer->notes);
        $this->assertStringContainsString('Payment Last 4: 4242', $customer->notes);
        $this->assertStringNotContainsString('4111111111111111', $customer->notes);
        $this->assertStringNotContainsString('portal-secret', $customer->notes);
        $this->assertStringNotContainsString('file-secret', $customer->notes);
        $this->assertStringNotContainsString('CVV', $customer->notes);
    }

    public function test_customer_xlsx_import_supports_same_format(): void
    {
        $agent = $this->user('agent', ['import.customers']);

        $this->actingAs($agent)->post('/import-export', [
            'resource' => 'customers',
            'file' => $this->xlsx([
                ['Customer ID', 'Name', 'Email', 'Business Name', 'Phone No', 'Billing Address', 'Amount', 'Plan', 'Software ', 'Payment Type', 'Last 4'],
                ['X-100', 'XLSX Customer', 'xlsx-customer@example.com', 'XLSX Business', '1555010202', '', '149.99', 'Monthly', 'Desktop', 'Master Card', '2805'],
            ]),
        ])->assertRedirect('/import-export')->assertSessionHas('import_summary.imported', 1);

        $customer = Customer::where('email', 'xlsx-customer@example.com')->firstOrFail();
        $this->assertSame('XLSX Customer', $customer->name);
        $this->assertSame('XLSX Business', $customer->company);
        $this->assertNull($customer->address);
        $this->assertStringContainsString('Amount: 149.99', $customer->notes);
        $this->assertStringContainsString('Payment Last 4: 2805', $customer->notes);
    }

    public function test_customer_xlsx_import_supports_headerless_ob_export_layout(): void
    {
        $agent = $this->user('agent', ['import.customers']);

        $this->actingAs($agent)->post('/import-export', [
            'resource' => 'customers',
            'file' => $this->xlsx([
                ['', '', '', '', '', '', '', '', '', '', '', '', '', 'Software'],
                ['10401214863', '', '', '', '2043625591', '', '', '', '', '', '44200', '1560.83', '', '', '838088023961111', '690215', '', '', '', '', 'unable to login', '', '1'],
                ['10501215378', 'Miss. Patricia Van Diepen', '', '', '5197199458', '', '', '', '', '', '44201', '495', '', '', '', '', '', '', '', '', '', '', '1'],
            ]),
        ])->assertRedirect('/import-export')->assertSessionHas('import_summary.imported', 2);

        $fallback = Customer::where('phone', '2043625591')->firstOrFail();
        $this->assertSame('Customer 10401214863', $fallback->name);
        $this->assertStringContainsString('Customer ID: 10401214863', $fallback->notes);
        $this->assertStringContainsString('Issue: unable to login', $fallback->notes);

        $named = Customer::where('phone', '5197199458')->firstOrFail();
        $this->assertSame('Miss. Patricia Van Diepen', $named->name);
        $this->assertStringContainsString('Amount: 495', $named->notes);
    }

    public function test_headerless_ob_customer_import_supports_owner_final_column(): void
    {
        $admin = $this->user('super-admin', ['import.customers']);
        $owner = $this->user('agent', [], ['name' => 'Assigned Agent', 'email' => 'assigned-agent@example.com']);

        $this->actingAs($admin)->post('/import-export', [
            'resource' => 'customers',
            'file' => $this->xlsx([
                ['', '', '', '', '', '', '', '', '', '', '', '', '', 'Software'],
                ['20501214863', 'Owned Customer', '', '', '2043625592', '', '', '', '', '', '44200', '1560.83', '', '', '', '', '', '', '', '', 'support issue', '', '1', '', '', $owner->email],
            ]),
        ])->assertRedirect('/import-export')->assertSessionHas('import_summary.imported', 1);

        $customer = Customer::where('phone', '2043625592')->firstOrFail();
        $this->assertSame($owner->id, $customer->owner_id);
    }

    public function test_customer_import_rejects_duplicate_customer_id_from_ob_data(): void
    {
        $agent = $this->user('agent', ['import.customers']);

        $this->actingAs($agent)->post('/import-export', [
            'resource' => 'customers',
            'file' => $this->xlsx([
                ['', '', '', '', '', '', '', '', '', '', '', '', '', 'Software'],
                ['30601214863', 'First OB Customer', '', '', '', '', '', '', '', '', '44200', '1560.83', '', '', '', '', '', '', '', '', 'first issue', '', '1'],
                ['30601214863', 'Duplicate OB Customer', '', '', '', '', '', '', '', '', '44201', '495', '', '', '', '', '', '', '', '', 'second issue', '', '1'],
            ]),
        ])->assertRedirect('/import-export')
            ->assertSessionHas('import_summary.imported', 1)
            ->assertSessionHas('import_summary.errors.0.row', 3);

        $this->assertSame(1, Customer::count());
        $this->assertDatabaseHas('customers', ['name' => 'First OB Customer']);
        $this->assertDatabaseMissing('customers', ['name' => 'Duplicate OB Customer']);
    }

    public function test_customer_import_rejects_existing_customer_id_from_notes(): void
    {
        $agent = $this->user('agent', ['import.customers']);
        Customer::create([
            'name' => 'Existing OB Customer',
            'notes' => "Customer ID: 40701214863\nAmount: 10",
            'owner_id' => $agent->id,
            'is_active' => true,
        ]);

        $this->actingAs($agent)->post('/import-export', [
            'resource' => 'customers',
            'file' => $this->csv("Customer ID,Name,Phone No\n40701214863,Duplicate Existing,\n"),
        ])->assertRedirect('/import-export')
            ->assertSessionHas('import_summary.imported', 0)
            ->assertSessionHas('import_summary.errors.0.row', 2);

        $this->assertSame(1, Customer::count());
    }

    public function test_customer_import_reports_missing_required_and_duplicate_rows(): void
    {
        $agent = $this->user('agent', ['import.customers']);
        Customer::create(['name' => 'Existing Customer', 'email' => 'existing-customer@example.com', 'phone' => '1555010303', 'owner_id' => $agent->id, 'is_active' => true]);

        $this->actingAs($agent)->post('/import-export', [
            'resource' => 'customers',
            'file' => $this->csv("Name,Email,Phone No\n,missing@example.com,1555010404\nDuplicate,existing-customer@example.com,1555010505\n"),
        ])->assertRedirect('/import-export')
            ->assertSessionHas('import_summary.imported', 0)
            ->assertSessionHas('import_summary.errors.0.row', 2)
            ->assertSessionHas('import_summary.errors.1.row', 3);

        $this->assertSame(1, Customer::count());
    }

    public function test_customer_import_handles_multiple_rows_and_optional_blanks(): void
    {
        $agent = $this->user('agent', ['import.customers']);

        $this->actingAs($agent)->post('/import-export', [
            'resource' => 'customers',
            'file' => $this->csv("Name,Email,Phone No,Business Name,Billing Address\nBlank Optional,blank@example.com,,,\nSecond Customer,second@example.com,1555010606,Second Co,\n"),
        ])->assertRedirect('/import-export')->assertSessionHas('import_summary.imported', 2);

        $blank = Customer::where('email', 'blank@example.com')->firstOrFail();
        $this->assertNull($blank->phone);
        $this->assertNull($blank->company);
        $this->assertNull($blank->address);
        $this->assertSame(2, Customer::count());
    }

    public function test_invalid_import_file_format_is_rejected(): void
    {
        $agent = $this->user('agent', ['import.customers']);

        $this->actingAs($agent)->post('/import-export', [
            'resource' => 'customers',
            'file' => UploadedFile::fake()->createWithContent('records.txt', 'Name,Email'),
        ])->assertStatus(422);

        $this->assertSame(0, Customer::count());
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
