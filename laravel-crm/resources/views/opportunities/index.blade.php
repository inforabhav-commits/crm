@extends('layouts.crm', ['title' => 'Opportunities'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">Opportunity Pipeline</h2>
                    <p class="text-muted mb-0">Track deal value, stage, owner, and expected close date.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @can('export.crm')<a class="btn btn-outline-primary" href="{{ route('import-export.export', 'opportunities') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"><i data-lucide="download" aria-hidden="true"></i><span>Export CSV</span></a>@endcan
                    <a class="btn btn-outline-primary" href="{{ route('opportunities.pipeline') }}"><i data-lucide="chart-no-axes-combined" aria-hidden="true"></i><span>Pipeline</span></a>
                    @can('opportunities.create')
                        <a class="btn btn-primary" href="{{ route('opportunities.create') }}"><i data-lucide="plus" aria-hidden="true"></i><span>Create Opportunity</span></a>
                    @endcan
                </div>
            </div>

            <form class="row g-2" method="get" action="{{ route('opportunities.index') }}">
                <div class="col-md-3">
                    <div class="crm-search-field">
                        <i data-lucide="search" aria-hidden="true"></i>
                        <input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search opportunity or customer">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="stage">
                        <option value="">All stages</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage->id }}" @selected((string) ($filters['stage'] ?? '') === (string) $stage->id)>{{ $stage->name }}</option>
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
                    <select class="form-select" name="status">
                        <option value="">Any status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="customer">
                        <option value="">All customers</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) ($filters['customer'] ?? '') === (string) $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-grid"><button class="btn btn-outline-primary" type="submit">Filter</button></div>
            </form>
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Deal</th><th>Customer</th><th>Stage</th><th>Owner</th><th>Value</th><th>Close</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($opportunities as $opportunity)
                        <tr>
                            <td><div class="fw-semibold">{{ $opportunity->name }}</div><small class="text-muted">{{ $opportunity->probability }}%</small></td>
                            <td>{{ $opportunity->customer?->name ?? '-' }}</td>
                            <td>{{ $opportunity->stage?->name ?? '-' }}</td>
                            <td>{{ $opportunity->owner?->name ?? '-' }}</td>
                            <td>{{ $opportunity->currency }} {{ $opportunity->amount ?? '0.00' }}</td>
                            <td>{{ $opportunity->expected_close_date?->format('Y-m-d') ?? '-' }}</td>
                            <td><span class="badge crm-status-badge {{ $opportunity->status === 'won' ? 'crm-status-success' : ($opportunity->status === 'lost' ? 'crm-status-danger' : 'crm-status-soft') }}">{{ ucfirst($opportunity->status) }}</span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('opportunities.show', $opportunity) }}">View</a>
                                @can('opportunities.edit')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('opportunities.edit', $opportunity) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                @include('partials.empty-state', [
                                    'icon' => 'briefcase-business',
                                    'title' => 'No opportunities found',
                                    'message' => 'Deals matching your filters will appear here.',
                                    'actionUrl' => auth()->user()->can('opportunities.create') ? route('opportunities.create') : null,
                                    'actionLabel' => 'Create Opportunity',
                                    'actionIcon' => 'plus',
                                ])
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $opportunities->links() }}
        </div>
    </section>
@endsection
