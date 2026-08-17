@extends('layouts.crm', ['title' => 'Leads'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">Lead Management</h2>
                    <p class="text-muted mb-0">Track lead ownership, status, source, and next follow-up.</p>
                </div>
                <div class="d-flex gap-2">
                    @can('export.crm')<a class="btn btn-outline-primary" href="{{ route('import-export.export', 'leads') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}">Export CSV</a>@endcan
                    @can('leads.create')<a class="btn btn-primary" href="{{ route('leads.create') }}">Create Lead</a>@endcan
                </div>
            </div>

            <form class="row g-2" method="get" action="{{ route('leads.index') }}">
                <div class="col-md-3">
                    <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, company, email, phone">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected((string) ($filters['status'] ?? '') === (string) $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="source">
                        <option value="">All sources</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected((string) ($filters['source'] ?? '') === (string) $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
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
                    <select class="form-select" name="priority">
                        <option value="">All priorities</option>
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-grid">
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
                        <th>Lead</th>
                        <th>Status</th>
                        <th>Source</th>
                        <th>Owner</th>
                        <th>Priority</th>
                        <th>Next Follow-up</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($leads as $lead)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $lead->name }}</div>
                                <small class="text-muted">{{ $lead->company ?: 'No company' }} · {{ $lead->email ?: $lead->phone ?: 'No contact' }}</small>
                            </td>
                            <td>{{ $lead->status?->name ?? '-' }}</td>
                            <td>{{ $lead->source?->name ?? '-' }}</td>
                            <td>{{ $lead->owner?->name ?? 'Unassigned' }}</td>
                            <td>{{ ucfirst($lead->priority) }}</td>
                            <td>{{ $lead->next_follow_up_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('leads.show', $lead) }}">View</a>
                                @can('leads.edit')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('leads.edit', $lead) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted" colspan="7">No leads found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $leads->links() }}
        </div>
    </section>
@endsection
