@extends('layouts.crm', ['title' => 'Customer 360'])

@section('content')
    @php
        $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
    @endphp
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm crm-record-hero">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div>
                    <div class="crm-record-kicker">Customer Summary</div>
                    <h2 class="h4 mb-1">{{ $customer->name }}</h2>
                    <p class="text-muted mb-2">{{ $customer->company ?: 'No company recorded' }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge crm-status-badge {{ $customer->is_active ? 'crm-status-success' : 'crm-status-secondary' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
                        <span class="badge crm-status-badge crm-status-soft">Owner: {{ $customer->owner?->name ?? 'Unassigned' }}</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('customers.index') }}">Back</a>
                    @can('calls.initiate')
                        @if ($canShowCallAction)
                            <form method="post" action="{{ route('customers.justcall.call', $customer) }}" data-click-to-call-form target="_blank" rel="noopener">
                                @csrf
                                <button class="btn btn-outline-primary" type="submit" data-click-to-call-button><i data-lucide="phone" aria-hidden="true"></i><span>Call</span></button>
                            </form>
                        @endif
                    @endcan
                    @if ($customer->email)
                        <a class="btn btn-outline-primary" href="mailto:{{ $customer->email }}"><i data-lucide="mail" aria-hidden="true"></i><span>Email</span></a>
                    @endif
                    @can('activities.create')
                        <a class="btn btn-outline-primary" href="{{ route('activities.create', ['customer_id' => $customer->id]) }}"><i data-lucide="clipboard-list" aria-hidden="true"></i><span>Add Activity</span></a>
                    @endcan
                    @can('contacts.create')
                        <a class="btn btn-outline-primary" href="{{ route('contacts.create', ['customer_id' => $customer->id]) }}"><i data-lucide="plus" aria-hidden="true"></i><span>Add Contact</span></a>
                    @endcan
                    @can('customers.edit')
                        <a class="btn btn-primary" href="{{ route('customers.edit', $customer) }}">Edit</a>
                    @endcan
                    @can('customers.delete')
                        <form method="post" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger" type="submit">Delete</button>
                        </form>
                    @endcan
                </div>
            </div>

            <div class="crm-record-tabs mb-3">
                <a href="#overview">Overview</a>
                <a href="#contacts">Contacts</a>
                <a href="#activities">Activities</a>
                <a href="#calls">Calls</a>
                <a href="#notes">Notes</a>
            </div>

            <div class="row g-3" id="overview">
                <div class="col-md-3"><div class="text-muted small">Owner</div><div>{{ $customer->owner?->name ?? 'Unassigned' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Customer ID</div><div>{{ $customer->external_customer_id ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Primary Contact</div><div>{{ $primaryContact?->name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Status</div><div><span class="badge crm-status-badge {{ $customer->is_active ? 'crm-status-success' : 'crm-status-secondary' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></div></div>
                <div class="col-md-3"><div class="text-muted small">Email</div><div>{{ $customer->email ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Phone</div><div>{{ $phonePrivacy->display($customer->phone, auth()->user()) }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Date</div><div>{{ $customer->sale_date ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Amount</div><div>{{ $customer->amount ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Plan</div><div>{{ $customer->plan ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Software</div><div>{{ $customer->software ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Liscense Number</div><div>{{ $customer->license_number ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Product Number</div><div>{{ $customer->product_number ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">File Password</div><div>{{ $customer->file_password ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Cloud Customer</div><div>{{ $customer->cloud_customer ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">User ID</div><div>{{ $customer->customer_user_id ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Password</div><div>{{ $customer->customer_password ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Sale Type</div><div>{{ $customer->sale_type ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">No of Cases</div><div>{{ $customer->no_of_cases ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Payment Type</div><div>{{ $customer->payment_type ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Last 4</div><div>{{ $customer->last_4 ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Card Type</div><div>{{ $customer->card_type ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">End</div><div>{{ $customer->end ?: '-' }}</div></div>
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
                <div class="col-md-6"><div class="text-muted small">Billing Address</div><div class="crm-note-box">{{ $customer->address ?: 'No billing address recorded.' }}</div></div>
                <div class="col-md-6"><div class="text-muted small">Issue</div><div class="crm-note-box">{{ $customer->issue ?: 'No issue recorded.' }}</div></div>
                <div class="col-md-6" id="notes"><div class="text-muted small">Notes</div><div class="crm-note-box">{{ $customer->notes ?: 'No notes recorded.' }}</div></div>
            </div>
        </div>
    </section>

    <div class="row g-4 mt-1" id="contacts">
        <div class="col-lg-4">
            <section class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Primary Contact</h2>
                    @if ($primaryContact)
                        <div class="fw-semibold">{{ $primaryContact->name }}</div>
                        <div class="text-muted">{{ $primaryContact->title ?: 'No title' }}</div>
                        <div class="mt-3 small">
                            <div><span class="text-muted">Email:</span> {{ $primaryContact->email ?: '-' }}</div>
                            <div><span class="text-muted">Phone:</span> {{ $phonePrivacy->display($primaryContact->phone, auth()->user()) }}</div>
                            <div><span class="text-muted">Mobile:</span> {{ $phonePrivacy->display($primaryContact->mobile, auth()->user()) }}</div>
                        </div>
                        <a class="btn btn-sm btn-outline-secondary mt-3" href="{{ route('contacts.show', $primaryContact) }}">View Contact</a>
                    @else
                        @include('partials.empty-state', ['icon' => 'user-round', 'title' => 'No primary contact', 'message' => 'Mark a contact as primary to surface it here.'])
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
                                    <td>{{ $phonePrivacy->display($contact->phone, auth()->user()) }}</td>
                                    <td>{{ $contact->is_active ? 'Active' : 'Inactive' }}</td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('contacts.show', $contact) }}">View</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5">@include('partials.empty-state', ['icon' => 'contact', 'title' => 'No contacts recorded', 'message' => 'Contacts linked to this customer will appear here.'])</td></tr>
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

    <div class="row g-4 mt-1" id="activities">
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
                        @include('partials.empty-state', ['icon' => 'calendar-clock', 'title' => 'No upcoming follow-ups', 'message' => 'Scheduled customer follow-ups will appear here.'])
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
                        @include('partials.empty-state', ['icon' => 'clipboard-list', 'title' => 'No overdue follow-ups', 'message' => 'Overdue customer activity will appear here.'])
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
                        <tr><td colspan="6">@include('partials.empty-state', ['icon' => 'clipboard-list', 'title' => 'No timeline entries yet', 'message' => 'Customer activities and follow-ups will appear here.'])</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mt-4" id="calls">
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
