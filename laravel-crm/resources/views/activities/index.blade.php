@extends('layouts.crm', ['title' => 'Activities'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">Activities</h2>
                    <p class="text-muted mb-0">Track calls, emails, meetings, visits, and follow-ups.</p>
                </div>
                <div class="d-flex gap-2">
                    @can('export.crm')<a class="btn btn-outline-primary" href="{{ route('import-export.export', 'activities') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}">Export CSV</a>@endcan
                    @can('activities.create')<a class="btn btn-primary" href="{{ route('activities.create') }}">Create Activity</a>@endcan
                </div>
            </div>

            <form class="row g-2" method="get" action="{{ route('activities.index') }}">
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="activity_type">
                        <option value="">All types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected((string) ($filters['activity_type'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
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
                    <select class="form-select" name="due">
                        <option value="">Any due date</option>
                        <option value="overdue" @selected(($filters['due'] ?? '') === 'overdue')>Overdue</option>
                        <option value="today" @selected(($filters['due'] ?? '') === 'today')>Today</option>
                        <option value="upcoming" @selected(($filters['due'] ?? '') === 'upcoming')>Upcoming</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="lead">
                        <option value="">All leads</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}" @selected((string) ($filters['lead'] ?? '') === (string) $lead->id)>{{ $lead->name }}</option>
                        @endforeach
                    </select>
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
                    <select class="form-select" name="contact">
                        <option value="">All contacts</option>
                        @foreach ($contacts as $contact)
                            <option value="{{ $contact->id }}" @selected((string) ($filters['contact'] ?? '') === (string) $contact->id)>{{ $contact->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="opportunity">
                        <option value="">All opportunities</option>
                        @foreach ($opportunities as $opportunity)
                            <option value="{{ $opportunity->id }}" @selected((string) ($filters['opportunity'] ?? '') === (string) $opportunity->id)>{{ $opportunity->name }}</option>
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
                        <th>Activity</th>
                        <th>Type</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Due</th>
                        <th>Related</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($activities as $activity)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $activity->subject }}</div>
                                <small class="text-muted">{{ ucfirst($activity->priority) }}</small>
                            </td>
                            <td>{{ $activity->type?->name ?? '-' }}</td>
                            <td>{{ $activity->assignedUser?->name ?? '-' }}</td>
                            <td>{{ ucfirst($activity->status) }}</td>
                            <td class="{{ $activity->is_overdue ? 'text-danger fw-semibold' : '' }}">{{ $activity->due_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                @if ($activity->related instanceof \App\Models\Lead)
                                    <a href="{{ route('leads.show', $activity->related) }}">{{ $activity->related->name }}</a>
                                @elseif ($activity->related instanceof \App\Models\Customer)
                                    <a href="{{ route('customers.show', $activity->related) }}">{{ $activity->related->name }}</a>
                                @elseif ($activity->related instanceof \App\Models\Contact)
                                    <a href="{{ route('contacts.show', $activity->related) }}">{{ $activity->related->name }}</a>
                                @elseif ($activity->related instanceof \App\Models\Opportunity)
                                    <a href="{{ route('opportunities.show', $activity->related) }}">{{ $activity->related->name }}</a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('activities.show', $activity) }}">View</a>
                                @can('activities.edit')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('activities.edit', $activity) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted" colspan="7">No activities found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $activities->links() }}
        </div>
    </section>
@endsection
