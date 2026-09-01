<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PhonePrivacyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ImportExportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('import.leads') || $request->user()->can('import.customers') || $request->user()->can('export.crm'), 403);

        return view('import-export.index', [
            'canImportLeads' => $request->user()->can('import.leads'),
            'canImportCustomers' => $request->user()->can('import.customers'),
            'canExport' => $request->user()->can('export.crm'),
            'summary' => session('import_summary'),
        ]);
    }

    public function import(Request $request, AuditService $audit)
    {
        $resource = $request->input('resource');
        $permission = $resource === 'leads' ? 'import.leads' : 'import.customers';
        $this->authorize($permission);

        $validated = $request->validate([
            'resource' => ['required', 'in:leads,customers,contacts'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['csv', 'xlsx'], true), 422, 'Only CSV and XLSX files are supported.');
        abort_if($file->getSize() > 10 * 1024 * 1024, 422, 'The import file is too large.');

        $rows = $this->readRows($file->getRealPath(), $extension);
        $summary = match ($validated['resource']) {
            'leads' => $this->importLeads($rows, $request->user()),
            'customers' => $this->importCustomers($rows, $request->user()),
            'contacts' => $this->importContacts($rows, $request->user()),
        };

        $audit->log('crm.imported', null, 'CRM import completed.', null, [
            'resource' => $validated['resource'],
            'format' => $extension,
            'imported' => $summary['imported'],
            'errors' => count($summary['errors']),
        ], $request->user(), $request);

        return Redirect::route('import-export.index')->with('import_summary', array_merge([
            'resource' => $validated['resource'],
        ], $summary));
    }

    public function export(Request $request, string $resource, AuditService $audit): StreamedResponse
    {
        $this->authorize('export.crm');
        abort_unless(in_array($resource, ['leads', 'customers', 'contacts', 'opportunities', 'activities', 'calls'], true), 404);

        $query = $this->exportQuery($request, $resource);
        $headers = $this->exportHeaders($resource);
        $filename = $resource.'-'.now()->format('Ymd-His').'.csv';

        $audit->log('crm.exported', null, 'CRM CSV export requested.', null, [
            'resource' => $resource,
            'filters' => $request->query(),
        ], $request->user(), $request);

        return response()->streamDownload(function () use ($query, $headers, $resource) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            $query->chunkById(500, function (Collection $records) use ($output, $resource) {
                foreach ($records as $record) {
                    fputcsv($output, array_map([$this, 'safeCell'], $this->exportRow($record, $resource)));
                }
            });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function importLeads(array $rows, User $user): array
    {
        return $this->importRows($rows, function (array $data, int $row) use ($user) {
            $name = $this->value($data, ['name', 'lead_name']);
            if ($name === '') throw new \RuntimeException('Name is required.');
            $email = strtolower($this->value($data, ['email']));
            $phone = $this->value($data, ['phone', 'phone_number']);
            $this->assertNoDuplicate(Lead::query(), $email, $phone, $row);
            $status = $this->masterValue('lead_status', $this->value($data, ['status', 'lead_status']));
            $source = $this->masterValue('lead_source', $this->value($data, ['source', 'lead_source']));
            $owner = $this->resolveOwner($this->value($data, ['owner', 'owner_email', 'owner_id']), $user);
            Lead::create([
                'name' => $name,
                'company' => $this->value($data, ['company']),
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'lead_status_id' => $status?->id ?? throw new \RuntimeException('A valid lead status is required.'),
                'lead_source_id' => $source?->id,
                'owner_id' => $owner->id,
                'priority' => $this->value($data, ['priority']) ?: 'normal',
                'next_follow_up_at' => $this->value($data, ['next_follow_up_at', 'follow_up_at']) ?: null,
                'notes' => $this->value($data, ['notes']),
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
            ]);
        });
    }

    private function importCustomers(array $rows, User $user): array
    {
        $rows = $this->normalizeCustomerRows($rows);

        return $this->importRows($rows, function (array $data, int $row) use ($user) {
            $name = $this->value($data, ['name', 'customer_name']);
            $businessName = $this->value($data, ['company', 'business_name']);
            $customerId = $this->value($data, ['customer_id']);
            if ($name === '' && $businessName !== '') {
                $name = $businessName;
            }
            if ($name === '' && $customerId !== '') {
                $name = 'Customer '.$customerId;
            }
            if ($name === '') throw new \RuntimeException('Name is required.');
            $email = strtolower($this->value($data, ['email']));
            $phone = $this->value($data, ['phone', 'phone_number', 'phone_no', 'phone_num']);
            $this->assertNoDuplicate(Customer::query(), $email, $phone, $row, $customerId);
            $owner = $this->resolveOwner($this->value($data, ['owner', 'owner_email', 'owner_id']), $user);
            Customer::create([
                'external_customer_id' => $customerId ?: null,
                'name' => $name,
                'company' => $businessName ?: null,
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'website' => $this->value($data, ['website']) ?: null,
                'industry' => $this->value($data, ['industry']) ?: null,
                'address' => $this->value($data, ['address', 'billing_address']) ?: null,
                'sale_date' => $this->value($data, ['date', 'sale_date']) ?: null,
                'amount' => $this->value($data, ['amount']) ?: null,
                'plan' => $this->value($data, ['plan']) ?: null,
                'software' => $this->value($data, ['software']) ?: null,
                'license_number' => $this->value($data, ['liscense_number', 'license_number']) ?: null,
                'product_number' => $this->value($data, ['product_number']) ?: null,
                'file_password' => $this->value($data, ['file_password']) ?: null,
                'cloud_customer' => $this->value($data, ['cloud_customer']) ?: null,
                'customer_user_id' => $this->value($data, ['user_id', 'customer_user_id']) ?: null,
                'customer_password' => $this->value($data, ['password', 'customer_password']) ?: null,
                'issue' => $this->value($data, ['issue']) ?: null,
                'sale_type' => $this->value($data, ['sale_type']) ?: null,
                'no_of_cases' => $this->value($data, ['no_of_cases']) ?: null,
                'payment_type' => $this->value($data, ['payment_type']) ?: null,
                'last_4' => $this->value($data, ['last_4']) ?: null,
                'card_type' => $this->value($data, ['card_type', 'cardtype']) ?: null,
                'end' => $this->value($data, ['end', 'end_date']) ?: null,
                'notes' => $this->customerNotes($data),
                'owner_id' => $owner->id,
                'is_active' => true,
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
            ]);
        });
    }

    private function importContacts(array $rows, User $user): array
    {
        return $this->importRows($rows, function (array $data, int $row) use ($user) {
            $firstName = $this->value($data, ['first_name', 'firstname']);
            if ($firstName === '') throw new \RuntimeException('First name is required.');
            $customer = $this->resolveCustomer($data, $user);
            $email = strtolower($this->value($data, ['email']));
            $phone = $this->value($data, ['phone', 'phone_number']);
            $duplicate = Contact::where('customer_id', $customer->id)->get()->first(fn (Contact $contact) => ($email !== '' && strtolower((string) $contact->email) === $email) || ($phone !== '' && $this->phone($contact->phone) === $this->phone($phone)));
            if ($duplicate) throw new \RuntimeException('A contact with this email or phone already exists for the customer.');
            Contact::create([
                'customer_id' => $customer->id,
                'first_name' => $firstName,
                'last_name' => $this->value($data, ['last_name', 'lastname']),
                'title' => $this->value($data, ['title']),
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'mobile' => $this->value($data, ['mobile']),
                'is_primary' => filter_var($this->value($data, ['is_primary', 'primary']), FILTER_VALIDATE_BOOLEAN),
                'is_active' => true,
                'notes' => $this->value($data, ['notes']),
                'created_by_id' => $user->id,
                'updated_by_id' => $user->id,
            ]);
        });
    }

    private function importRows(array $rows, callable $callback): array
    {
        $summary = ['imported' => 0, 'errors' => []];
        if (count($rows) < 2) return $summary;
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), array_shift($rows));
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            if (! array_filter($row, fn ($value) => trim((string) $value) !== '')) continue;
            $data = [];
            foreach ($headers as $column => $header) if ($header !== '') $data[$header] = trim((string) ($row[$column] ?? ''));
            try {
                $callback($data, $rowNumber);
                $summary['imported']++;
            } catch (\Throwable $exception) {
                $summary['errors'][] = ['row' => $rowNumber, 'message' => $exception->getMessage()];
            }
        }
        return $summary;
    }

    private function normalizeCustomerRows(array $rows): array
    {
        if (count($rows) < 2) {
            return $rows;
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader($header), $rows[0]);
        if (array_intersect($headers, ['name', 'customer_name'])) {
            return $rows;
        }

        $dataRows = array_values(array_filter($rows, function (array $row, int $index) {
            if ($index === 0) {
                return false;
            }

            return $this->looksLikeHeaderlessCustomerRow($row);
        }, ARRAY_FILTER_USE_BOTH));

        if ($dataRows === []) {
            return $rows;
        }

        return array_merge([$this->headerlessCustomerHeaders()], $dataRows);
    }

    private function looksLikeHeaderlessCustomerRow(array $row): bool
    {
        $customerId = trim((string) ($row[0] ?? ''));
        $name = trim((string) ($row[1] ?? ''));
        $phone = $this->phone((string) ($row[4] ?? ''));
        $amount = trim((string) ($row[9] ?? $row[7] ?? $row[11] ?? ''));

        return $customerId !== ''
            && (is_numeric($customerId) || $name !== '' || $phone !== '')
            && ($name !== '' || $phone !== '' || $amount !== '');
    }

    private function headerlessCustomerHeaders(): array
    {
        return [
            'Customer ID',
            'Name',
            'Email',
            'Business Name',
            'Phone No',
            'Billing Address',
            'Reserved 1',
            'Reserved 2',
            'Date',
            'Amount',
            'Plan',
            'Software',
            'Product Number',
            'Cloud Customer',
            'Reserved 3',
            'Reserved 4',
            'Issue',
            'Sale Type',
            'Reserved 5',
            'Reserved 6',
            'No of Cases',
            'Payment Type',
            'Last 4',
            'Card Type',
            'End',
            'Owner',
        ];
    }

    private function readRows(string $path, string $extension): array
    {
        return $extension === 'xlsx' ? $this->readXlsx($path) : $this->readCsv($path);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        abort_if($handle === false, 422, 'The CSV file could not be read.');
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) $rows[] = $row;
        fclose($handle);
        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        abort_unless($zip->open($path) === true, 422, 'The XLSX file could not be read.');

        try {
            $sharedStrings = $this->xlsxSharedStrings($zip);
            $sheetPath = $this->firstWorksheetPath($zip);
            abort_unless($sheetPath, 422, 'The XLSX file does not contain a worksheet.');

            $xml = simplexml_load_string($zip->getFromName($sheetPath));
            abort_unless($xml, 422, 'The XLSX worksheet could not be read.');

            $rows = [];
            foreach ($xml->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $reference = (string) $cell['r'];
                    $column = $this->xlsxColumnIndex($reference);
                    if ($column === null) {
                        $column = count($values);
                    }
                    $values[$column] = $this->xlsxCellValue($cell, $sharedStrings);
                }

                if ($values !== []) {
                    ksort($values);
                    $rowValues = [];
                    for ($column = 0, $lastColumn = max(array_keys($values)); $column <= $lastColumn; $column++) {
                        $rowValues[] = (string) ($values[$column] ?? '');
                    }
                    $rows[] = $rowValues;
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function xlsxSharedStrings(ZipArchive $zip): array
    {
        $content = $zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) {
            return [];
        }

        $xml = simplexml_load_string($content);
        if (! $xml) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $item) {
            $text = '';
            foreach ($item->xpath('.//t') ?: [] as $part) {
                $text .= (string) $part;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (preg_match('/^xl\/worksheets\/sheet\d+\.xml$/', $name)) {
                return $name;
            }
        }

        return null;
    }

    private function xlsxCellValue(\SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];
        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        $value = trim((string) ($cell->v ?? ''));
        if ($type === 's' && $value !== '') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function xlsxColumnIndex(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return null;
        }

        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function resolveOwner(string $value, User $user): User
    {
        $allowed = $this->ownerOptions($user);
        if ($value === '') return $user;
        $owner = is_numeric($value) ? $allowed->firstWhere('id', (int) $value) : $allowed->first(fn (User $candidate) => strtolower($candidate->email) === strtolower($value) || strtolower($candidate->name) === strtolower($value));
        if (! $owner) throw new \RuntimeException('Owner is invalid or outside your permitted hierarchy.');
        return $owner;
    }

    private function resolveCustomer(array $data, User $user): Customer
    {
        $value = $this->value($data, ['customer_id', 'customer', 'customer_email', 'customer_name']);
        $customer = Customer::visibleTo($user)->when(is_numeric($value), fn ($query) => $query->whereKey((int) $value), fn ($query) => $query->where(fn ($nested) => $nested->where('email', $value)->orWhere('name', $value)))->first();
        if (! $customer) throw new \RuntimeException('A valid permitted customer relationship is required.');
        return $customer;
    }

    private function assertNoDuplicate(Builder $query, string $email, string $phone, int $row, ?string $customerId = null): void
    {
        $emailExists = $email !== '' && (clone $query)->whereRaw('lower(email) = ?', [$email])->exists();
        $phoneExists = $phone !== '' && (clone $query)->get()->contains(fn ($record) => $this->phone($record->phone) === $this->phone($phone));
        $customerIdExists = $customerId !== null
            && $customerId !== ''
            && (clone $query)->get()->contains(fn ($record) => (string) $record->external_customer_id === $customerId || $this->customerIdFromNotes($record->notes) === $customerId);
        if ($emailExists || $phoneExists || $customerIdExists) throw new \RuntimeException('A record with this email, phone, or customer ID already exists.');
    }

    private function masterValue(string $type, string $value): ?CrmMasterValue
    {
        if ($value === '') return null;
        return CrmMasterValue::where('type', $type)->where(fn ($query) => $query->where('slug', str($value)->slug())->orWhere('name', $value)->orWhere('id', is_numeric($value) ? (int) $value : 0))->where('is_active', true)->first();
    }

    private function exportQuery(Request $request, string $resource): Builder
    {
        $user = $request->user();
        $query = match ($resource) {
            'leads' => Lead::with(['status', 'source', 'owner'])->visibleTo($user),
            'customers' => Customer::with('owner')->visibleTo($user),
            'contacts' => Contact::with('customer')->visibleTo($user),
            'opportunities' => Opportunity::with(['customer', 'stage', 'owner'])->visibleTo($user),
            'activities' => Activity::with(['type', 'assignedUser'])->visibleTo($user),
            'calls' => CallLog::with('user')->visibleTo($user),
        };
        $this->applyTeamFilter($query, $request, $resource, $user);
        $this->applyExportFilters($query, $request, $resource);
        $query->orderBy($query->getModel()->getTable().'.id');
        return $query;
    }

    private function applyExportFilters(Builder $query, Request $request, string $resource): void
    {
        if ($request->filled('owner')) $query->where($resource === 'activities' ? 'assigned_user_id' : ($resource === 'calls' ? 'user_id' : 'owner_id'), $request->integer('owner'));
        $canSearchPhone = app(PhonePrivacyService::class)->canViewFullPhone($request->user());

        if ($resource === 'leads') {
            if ($request->filled('status')) $query->where('lead_status_id', $request->integer('status'));
            if ($request->filled('source')) $query->where('lead_source_id', $request->integer('source'));
            if ($request->filled('search')) $query->where(function ($q) use ($request, $canSearchPhone) {
                $q->where('name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%');
                if ($canSearchPhone) $q->orWhere('phone', 'like', '%'.$request->string('search').'%');
            });
        } elseif ($resource === 'customers') {
            if ($request->filled('search')) $query->where(function ($q) use ($request, $canSearchPhone) {
                $q->where('name', 'like', '%'.$request->string('search').'%')->orWhere('company', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%');
                if ($canSearchPhone) $q->orWhere('phone', 'like', '%'.$request->string('search').'%');
            });
            if ($request->filled('industry')) $query->where('industry', 'like', '%'.$request->string('industry').'%');
            if ($request->filled('status')) $query->where('is_active', $request->boolean('status'));
        } elseif ($resource === 'contacts') {
            if ($request->filled('search')) $query->where(function ($q) use ($request, $canSearchPhone) {
                $q->where('first_name', 'like', '%'.$request->string('search').'%')->orWhere('last_name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%');
                if ($canSearchPhone) $q->orWhere('phone', 'like', '%'.$request->string('search').'%');
            });
            if ($request->filled('customer')) $query->where('customer_id', $request->integer('customer'));
            if ($request->filled('status')) $query->where('is_active', $request->boolean('status'));
        } elseif ($resource === 'opportunities') {
            if ($request->filled('search')) $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%')->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$request->string('search').'%')));
            if ($request->filled('stage')) $query->where('stage_id', $request->integer('stage'));
            if ($request->filled('status')) $query->where('status', $request->string('status'));
            if ($request->filled('customer')) $query->where('customer_id', $request->integer('customer'));
        } elseif ($resource === 'activities') {
            if ($request->filled('status')) $query->where('status', $request->string('status'));
            if ($request->filled('activity_type')) $query->where('activity_type_id', $request->integer('activity_type'));
            if ($request->filled('from')) $query->where('due_at', '>=', $request->date('from')->startOfDay());
            if ($request->filled('to')) $query->where('due_at', '<=', $request->date('to')->endOfDay());
            if ($request->input('due') === 'overdue') $query->where('status', 'pending')->where('due_at', '<', now());
            if ($request->input('due') === 'upcoming') $query->where('status', 'pending')->where('due_at', '>=', now());
            if ($request->input('due') === 'today') $query->whereDate('due_at', today());
        } elseif ($resource === 'calls') {
            if ($request->filled('agent')) $query->where('user_id', $request->integer('agent'));
            if ($request->filled('direction')) $query->where('direction', $request->string('direction'));
            if ($request->filled('status')) $query->where('status', $request->string('status'));
            if ($request->filled('date')) $query->whereDate('last_event_at', $request->date('date'));
            if ($request->filled('from')) $query->where('last_event_at', '>=', $request->date('from')->startOfDay());
            if ($request->filled('to')) $query->where('last_event_at', '<=', $request->date('to')->endOfDay());
            if ($request->input('match') === 'matched') $query->where(fn ($match) => $match->whereNotNull('lead_id')->orWhereNotNull('customer_id')->orWhereNotNull('contact_id')->orWhereNotNull('opportunity_id'));
            if ($request->input('match') === 'unmatched') $query->whereNull('lead_id')->whereNull('customer_id')->whereNull('contact_id')->whereNull('opportunity_id');
        }
    }

    private function applyTeamFilter(Builder $query, Request $request, string $resource, User $user): void
    {
        if (! $request->filled('team')) return;
        $team = Team::whereKey($request->integer('team'))->where('is_active', true)->first();
        $permitted = $this->ownerOptions($user)->pluck('id')->all();
        $teamUserIds = $team ? $team->members()->pluck('users.id')->all() : [];
        $ids = array_values(array_intersect($permitted, $teamUserIds));
        $column = in_array($resource, ['activities', 'calls'], true) ? ($resource === 'activities' ? 'assigned_user_id' : 'user_id') : 'owner_id';
        $query->whereIn($column, $ids);
    }

    private function exportHeaders(string $resource): array
    {
        return match ($resource) {
            'leads' => ['Name', 'Company', 'Email', 'Phone', 'Status', 'Source', 'Owner', 'Priority', 'Created At'],
            'customers' => ['Customer ID', 'Name', 'Email', 'Business Name', 'Phone No', 'Billing Address', 'Date', 'Amount', 'Plan', 'Software', 'Liscense Number', 'Product Number', 'File Password', 'Cloud Customer', 'User ID', 'Password', 'Issue', 'Sale Type', 'No of Cases', 'Payment Type', 'Last 4', 'Card Type', 'End', 'Owner', 'Active', 'Created At'],
            'contacts' => ['First Name', 'Last Name', 'Title', 'Email', 'Phone', 'Mobile', 'Customer', 'Active', 'Created At'],
            'opportunities' => ['Name', 'Customer', 'Stage', 'Owner', 'Amount', 'Currency', 'Probability', 'Status', 'Expected Close Date', 'Closed At', 'Created At'],
            'activities' => ['Subject', 'Type', 'Status', 'Priority', 'Due At', 'Assigned User', 'Related Type', 'Related ID', 'Created At'],
            'calls' => ['External Call ID', 'Direction', 'Status', 'Agent', 'Customer Number', 'Started At', 'Ended At', 'Duration Seconds', 'Disposition', 'CRM Notes', 'Last Event At'],
        };
    }

    private function exportRow($record, string $resource): array
    {
        $phonePrivacy = app(PhonePrivacyService::class);
        $user = request()->user();

        return match ($resource) {
            'leads' => [$record->name, $record->company, $record->email, $phonePrivacy->exportValue($record->phone, $user), $record->status?->name, $record->source?->name, $record->owner?->name, $record->priority, $record->created_at],
            'customers' => [$record->external_customer_id, $record->name, $record->email, $record->company, $phonePrivacy->exportValue($record->phone, $user), $record->address, $record->sale_date, $record->amount, $record->plan, $record->software, $record->license_number, $record->product_number, $record->file_password, $record->cloud_customer, $record->customer_user_id, $record->customer_password, $record->issue, $record->sale_type, $record->no_of_cases, $record->payment_type, $record->last_4, $record->card_type, $record->end, $record->owner?->name, $record->is_active ? 'yes' : 'no', $record->created_at],
            'contacts' => [$record->first_name, $record->last_name, $record->title, $record->email, $phonePrivacy->exportValue($record->phone, $user), $phonePrivacy->exportValue($record->mobile, $user), $record->customer?->name, $record->is_active ? 'yes' : 'no', $record->created_at],
            'opportunities' => [$record->name, $record->customer?->name, $record->stage?->name, $record->owner?->name, $record->amount, $record->currency, $record->probability, $record->status, $record->expected_close_date, $record->closed_at, $record->created_at],
            'activities' => [$record->subject, $record->type?->name, $record->status, $record->priority, $record->due_at, $record->assignedUser?->name, $record->related_type, $record->related_id, $record->created_at],
            'calls' => [$record->external_call_id, $record->direction, $record->status, $record->user?->name, $phonePrivacy->exportValue($record->customer_number, $user), $record->started_at, $record->ended_at, $record->duration_seconds, $record->disposition, $record->crm_notes, $record->last_event_at],
        };
    }

    private function safeCell($value): string
    {
        $value = (string) ($value ?? '');
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function value(array $data, array $keys): string { foreach ($keys as $key) { $key = $this->normalizeHeader($key); if (($data[$key] ?? '') !== '') return trim((string) $data[$key]); } return ''; }
    private function normalizeHeader($value): string { return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $value))); }
    private function phone(?string $value): string { return preg_replace('/\D+/', '', (string) $value); }
    private function customerIdFromNotes(?string $notes): ?string { return preg_match('/(?:^|\R)Customer ID:\s*(.+?)(?:\R|$)/', (string) $notes, $matches) ? trim($matches[1]) : null; }
    private function ownerOptions(User $user): Collection { $ids = $user->hasPermission('leads.assign') || $user->hasRole('admin') || $user->hasRole('super-admin') ? User::where('is_active', true)->pluck('id')->all() : array_merge([$user->id], $user->reportingTreeUserIds()); return User::whereIn('id', array_unique($ids))->where('is_active', true)->get(); }

    private function customerNotes(array $data): ?string
    {
        $baseNotes = $this->value($data, ['notes']);
        $fields = [
            'Customer ID' => ['customer_id'],
            'Sale Date' => ['date'],
            'Amount' => ['amount'],
            'Plan' => ['plan'],
            'Software' => ['software'],
            'License Number' => ['liscense_number', 'license_number'],
            'Product Number' => ['product_number'],
            'Cloud Customer' => ['cloud_customer'],
            'Issue' => ['issue'],
            'Sale Type' => ['sale_type'],
            'No of Cases' => ['no_of_cases'],
            'Payment Type' => ['payment_type'],
            'Payment Last 4' => ['last_4'],
            'Card Type' => ['card_type', 'cardtype'],
            'End' => ['end', 'end_date'],
        ];

        $lines = [];
        foreach ($fields as $label => $keys) {
            $value = $this->value($data, $keys);
            if ($value !== '') {
                $lines[] = $label.': '.$value;
            }
        }

        $notes = trim(implode("\n", array_filter([$baseNotes, implode("\n", $lines)])));

        return $notes === '' ? null : $notes;
    }
}
