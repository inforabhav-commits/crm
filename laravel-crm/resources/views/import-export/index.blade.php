@extends('layouts.crm', ['title' => 'Import / Export'])

@section('content')
    @if ($summary)
        <section class="alert alert-{{ count($summary['errors']) ? 'warning' : 'success' }}">
            <strong>{{ ucfirst($summary['resource']) }} import:</strong> {{ $summary['imported'] }} row(s) imported, {{ count($summary['errors']) }} row(s) rejected.
            @if (count($summary['errors']))
                <ul class="mb-0 mt-2">@foreach ($summary['errors'] as $error)<li>Row {{ $error['row'] }}: {{ $error['message'] }}</li>@endforeach</ul>
            @endif
        </section>
    @endif

    @if ($canImportLeads || $canImportCustomers)
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h2 class="h5">Import CSV</h2>
                <p class="text-muted small">CSV only, maximum 10 MB. Existing records with the same email or phone are rejected to prevent avoidable duplicates.</p>
                <form method="post" action="{{ route('import-export.import') }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-md-3"><label class="form-label" for="resource">Data type</label><select class="form-select" id="resource" name="resource" required>@if ($canImportLeads)<option value="leads">Leads</option>@endif @if ($canImportCustomers)<option value="customers">Customers</option><option value="contacts">Contacts</option>@endif</select></div>
                    <div class="col-md-6"><label class="form-label" for="file">CSV file</label><input class="form-control" id="file" type="file" name="file" accept=".csv,text/csv" required></div>
                    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary" type="submit">Import CSV</button></div>
                </form>
                <div class="small text-muted mt-3">Lead columns: name, email, phone, status, source, owner, priority. Customer columns: name, email, phone, owner. Contact columns: first_name, last_name, email, phone, customer_id or customer_email.</div>
            </div>
        </section>
    @endif

    @if ($canExport)
        <section class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Export CRM data</h2>
                <p class="text-muted small">Exports are CSV files containing only records visible to your account. Use the resource page filters when exporting a filtered result set.</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach (['leads' => 'Leads', 'customers' => 'Customers', 'contacts' => 'Contacts', 'opportunities' => 'Opportunities', 'activities' => 'Activities', 'calls' => 'Call logs'] as $resource => $label)
                        <a class="btn btn-outline-primary" href="{{ route('import-export.export', $resource) }}">Export {{ $label }}</a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
