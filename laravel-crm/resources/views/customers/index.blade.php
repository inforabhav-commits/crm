@extends('layouts.crm', ['title' => 'Customers'])

@section('content')
    @php
        $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
    @endphp
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">Customers / Accounts</h2>
                    <p class="text-muted mb-0">Manage customer accounts, ownership, profile notes, and contacts.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @can('export.crm')<a class="btn btn-outline-primary" href="{{ route('import-export.export', 'customers') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"><i data-lucide="download" aria-hidden="true"></i><span>Export CSV</span></a>@endcan
                    @can('customers.create')<a class="btn btn-primary" href="{{ route('customers.create') }}"><i data-lucide="plus" aria-hidden="true"></i><span>Create Customer</span></a>@endcan
                </div>
            </div>

            <form class="row g-2" method="get" action="{{ route('customers.index') }}">
                <div class="col-md-3">
                    <div class="crm-search-field">
                        <i data-lucide="search" aria-hidden="true"></i>
                        <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, company, email, phone">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="owner">
                        <option value="">All owners</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}" @selected((string) ($filters['owner'] ?? '') === (string) $owner->id)>{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">Any status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input class="form-control" name="industry" value="{{ $filters['industry'] ?? '' }}" placeholder="Industry">
                </div>
                <div class="col-md-3">
                    <label for="from_date" class="form-label">From Date</label>
                    <input id="from_date" class="form-control" type="date" name="from_date" value="{{ old('from_date', $filters['from_date'] ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label for="to_date" class="form-label">To Date</label>
                    <input id="to_date" class="form-control" type="date" name="to_date" value="{{ old('to_date', $filters['to_date'] ?? '') }}">
                </div>
                <div class="col-md-2 d-grid align-self-end">
                    <button class="btn btn-outline-primary" type="submit">Filter</button>
                    <a href="{{ route('customers.index') }}" class="btn btn-link">Clear filters</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Business Name</th>
                        <th>Phone No</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->external_customer_id ?: '-' }}</td>
                            <td class="fw-semibold">{{ $customer->name }}</td>
                            <td>{{ $customer->email ?: '-' }}</td>
                            <td>{{ $customer->company ?: '-' }}</td>
                            <td>{{ $customer->phone ? $phonePrivacy->display($customer->phone, auth()->user()) : '-' }}</td>
                            <td><span class="badge crm-status-badge {{ $customer->is_active ? 'crm-status-success' : 'crm-status-secondary' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end text-nowrap">
                                @can('calls.initiate')
                                    @if ($canCallCustomers && app(\App\Services\Integrations\JustCall\JustCallClickToCallService::class)->canShowFor($customer))
                                        <form class="d-inline" method="post" action="{{ route('customers.justcall.call', $customer) }}" data-click-to-call-form>
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary" type="submit" title="Call Customer" aria-label="Call Customer" data-click-to-call-button><i data-lucide="phone" aria-hidden="true"></i></button>
                                        </form>
                                    @endif
                                @endcan
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('customers.show', $customer) }}">View</a>
                                @can('customers.edit')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('customers.edit', $customer) }}">Edit</a>
                                @endcan
                                @can('customers.delete')
                                    <form class="d-inline" method="post" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('partials.empty-state', [
                                    'icon' => 'contact',
                                    'title' => 'No customers found',
                                    'message' => 'Customer accounts matching your filters will appear here.',
                                    'actionUrl' => auth()->user()->can('customers.create') ? route('customers.create') : null,
                                    'actionLabel' => 'Create Customer',
                                    'actionIcon' => 'plus',
                                ])
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $customers->links() }}
        </div>
    </section>
@endsection
