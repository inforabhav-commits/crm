@extends('layouts.crm', ['title' => 'Contacts'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">Contacts</h2>
                    <p class="text-muted mb-0">Manage people attached to customer accounts.</p>
                </div>
                @can('contacts.create')
                    <a class="btn btn-primary" href="{{ route('contacts.create') }}">Create Contact</a>
                @endcan
            </div>

            <form class="row g-2" method="get" action="{{ route('contacts.index') }}">
                <div class="col-md-4">
                    <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email, phone">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="customer">
                        <option value="">All customers</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) ($filters['customer'] ?? '') === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">Any status</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    </select>
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
                        <th>Name</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($contacts as $contact)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $contact->name }}</div>
                                <small class="text-muted">{{ $contact->title ?: 'No title' }}</small>
                            </td>
                            <td>{{ $contact->customer?->name ?? '-' }}</td>
                            <td>{{ $contact->email ?: '-' }}</td>
                            <td>{{ $contact->phone ?: '-' }}</td>
                            <td>{{ $contact->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('contacts.show', $contact) }}">View</a>
                                @can('contacts.edit')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('contacts.edit', $contact) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted" colspan="6">No contacts found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $contacts->links() }}
        </div>
    </section>
@endsection
