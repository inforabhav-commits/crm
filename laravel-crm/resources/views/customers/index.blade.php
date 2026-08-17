@extends('layouts.crm', ['title' => 'Customers'])

@section('content')
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
                <div class="d-flex gap-2">
                    @can('export.crm')<a class="btn btn-outline-primary" href="{{ route('import-export.export', 'customers') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}">Export CSV</a>@endcan
                    @can('customers.create')<a class="btn btn-primary" href="{{ route('customers.create') }}">Create Customer</a>@endcan
                </div>
            </div>

            <form class="row g-2" method="get" action="{{ route('customers.index') }}">
                <div class="col-md-3">
                    <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, company, email, phone">
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
                <div class="col-md-2 d-grid">
                    <button class="btn btn-outline-primary" type="submit">Filter</button>
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
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Industry</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $customer->name }}</div>
                                <small class="text-muted">{{ $customer->company ?: 'No company' }}</small>
                            </td>
                            <td>{{ $customer->email ?: $customer->phone ?: '-' }}</td>
                            <td>{{ $customer->industry ?: '-' }}</td>
                            <td>{{ $customer->owner?->name ?? 'Unassigned' }}</td>
                            <td>{{ $customer->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('customers.show', $customer) }}">View</a>
                                @can('customers.edit')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('customers.edit', $customer) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted" colspan="6">No customers found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $customers->links() }}
        </div>
    </section>
@endsection
