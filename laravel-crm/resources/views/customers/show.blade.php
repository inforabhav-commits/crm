@extends('layouts.crm', ['title' => 'Customer 360'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $customer->name }}</h2>
                    <p class="text-muted mb-0">{{ $customer->company ?: 'No company recorded' }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('customers.index') }}">Back</a>
                    @can('calls.initiate')
                        @if ($canShowCallAction)
                            <form method="post" action="{{ route('customers.justcall.call', $customer) }}" data-click-to-call-form>
                                @csrf
                                <button class="btn btn-outline-primary" type="submit" data-click-to-call-button>Call</button>
                            </form>
                        @endif
                    @endcan
                    @can('activities.create')
                        <a class="btn btn-outline-primary" href="{{ route('activities.create', ['customer_id' => $customer->id]) }}">Add Activity</a>
                        <a class="btn btn-outline-primary" href="{{ route('activities.create', ['customer_id' => $customer->id]) }}">Add Follow-up</a>
                    @endcan
                    @can('contacts.create')
                        <a class="btn btn-outline-primary" href="{{ route('contacts.create', ['customer_id' => $customer->id]) }}">Add Contact</a>
                    @endcan
                    @can('customers.edit')
                        <a class="btn btn-primary" href="{{ route('customers.edit', $customer) }}">Edit</a>
                    @endcan
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">Owner</div><div>{{ $customer->owner?->name ?? 'Unassigned' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Primary Contact</div><div>{{ $primaryContact?->name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Status</div><div>{{ $customer->is_active ? 'Active' : 'Inactive' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Email</div><div>{{ $customer->email ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Phone</div><div>{{ $customer->phone ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Industry</div><div>{{ $customer->industry ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Website</div><div>{{ $customer->website ?: '-' }}</div></div>
                <div class="col-md-3">
                    <div class="text-muted small">Source Lead</div>
                    <div>
                        @if ($customer->convertedFromLead)
                            <a href="{{ route('leads.show', $customer->convertedFromLead) }}">{{ $customer->convertedFromLead->name }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-md-3"><div class="text-muted small">Contacts</div><div>{{ $summary['contacts'] }} total / {{ $summary['active_contacts'] }} active</div></div>
                <div class="col-md-3"><div class="text-muted small">Activities</div><div>{{ $summary['activities'] }} total / {{ $summary['pending_activities'] }} pending</div></div>
                <div class="col-md-6"><div class="text-muted small">Address</div><div class="border rounded p-3 bg-light">{{ $customer->address ?: 'No address recorded.' }}</div></div>
                <div class="col-md-6"><div class="text-muted small">Notes</div><div class="border rounded p-3 bg-light">{{ $customer->notes ?: 'No notes recorded.' }}</div></div>
            </div>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <div class="col-lg-4">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Primary Contact</h2>
                    @if ($primaryContact)
                        <div class="fw-semibold">{{ $primaryContact->name }}</div>
                        <div class="text-muted">{{ $primaryContact->title ?: 'No title' }}</div>
                        <div class="mt-3 small">
                            <div><span class="text-muted">Email:</span> {{ $primaryContact->email ?: '-' }}</div>
                            <div><span class="text-muted">Phone:</span> {{ $primaryContact->phone ?: '-' }}</div>
                            <div><span class="text-muted">Mobile:</span> {{ $primaryContact->mobile ?: '-' }}</div>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary mt-3" href="{{ route('contacts.show', $primaryContact) }}">View Contact</a>
                    @else
                        <div class="text-muted">No primary contact recorded.</div>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Related Contacts</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                            @forelse ($customer->contacts as $contact)
                                <tr>
                                    <td>{{ $contact->name }} @if($contact->is_primary)<span class="badge text-bg-primary rounded-1">Primary</span>@endif</td>
                                    <td>{{ $contact->email ?: '-' }}</td>
                                    <td>{{ $contact->phone ?: '-' }}</td>
                                    <td>{{ $contact->is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('contacts.show', $contact) }}">View</a></td>
                                </tr>
                            @empty
                                <tr><td class="text-muted" colspan="5">No contacts recorded.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Related Records</h2>
                    <div class="row g-3">
                        <div class="col-6"><div class="text-muted small">Contacts</div><div class="h5 mb-0">{{ $summary['contacts'] }}</div></div>
                        <div class="col-6"><div class="text-muted small">Activities</div><div class="h5 mb-0">{{ $summary['activities'] }}</div></div>
                        <div class="col-6"><div class="text-muted small">Pending</div><div class="h5 mb-0">{{ $summary['pending_activities'] }}</div></div>
                        <div class="col-6"><div class="text-muted small">Calls</div><div class="h5 mb-0">{{ $summary['calls'] }}</div></div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Upcoming Follow-ups</h2>
                    @forelse ($upcomingActivities as $activity)
                        <div class="border-bottom pb-2 mb-2">
                            <a href="{{ route('activities.show', $activity) }}">{{ $activity->subject }}</a>
                            <div class="small text-muted">{{ $activity->type?->name ?? 'Activity' }} - {{ $activity->due_at?->format('Y-m-d H:i') }} - {{ $activity->assignedUser?->name ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No upcoming follow-ups.</div>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Overdue Follow-ups</h2>
                    @forelse ($overdueActivities as $activity)
                        <div class="border-bottom pb-2 mb-2">
                            <a class="text-danger fw-semibold" href="{{ route('activities.show', $activity) }}">{{ $activity->subject }}</a>
                            <div class="small text-muted">{{ $activity->type?->name ?? 'Activity' }} - {{ $activity->due_at?->format('Y-m-d H:i') }} - {{ $activity->assignedUser?->name ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No overdue follow-ups.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Communication Timeline</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>When</th>
                        <th>Activity</th>
                        <th>Related</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($timelineActivities as $activity)
                        <tr>
                            <td>{{ $activity->due_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="fw-semibold">{{ $activity->subject }}</div>
                                <small class="text-muted">{{ $activity->type?->name ?? 'Activity' }} - {{ ucfirst($activity->priority) }}</small>
                            </td>
                            <td>
                                @if ($activity->related instanceof \App\Models\Customer)
                                    Customer
                                @elseif ($activity->related instanceof \App\Models\Contact)
                                    Contact: {{ $activity->related->name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $activity->assignedUser?->name ?? '-' }}</td>
                            <td>{{ ucfirst($activity->status) }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('activities.show', $activity) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="6">No timeline entries yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h5 mb-0">Call History</h2>
                @can('calls.view')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('calls.index', ['match' => 'matched']) }}">View Calls</a>
                @endcan
            </div>
            @include('call_logs._table', ['callLogs' => $callLogs])
        </div>
    </section>
@endsection
