@extends('layouts.crm', ['title' => 'Lead Detail'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $lead->name }}</h2>
                    <p class="text-muted mb-0">{{ $lead->company ?: 'No company recorded' }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('leads.index') }}">Back</a>
                    @can('calls.initiate')
                        @if ($canShowCallAction)
                            <form method="post" action="{{ route('leads.justcall.call', $lead) }}" data-click-to-call-form>
                                @csrf
                                <button class="btn btn-outline-primary" type="submit" data-click-to-call-button>Call</button>
                            </form>
                        @endif
                    @endcan
                    @can('leads.edit')
                        <a class="btn btn-primary" href="{{ route('leads.edit', $lead) }}">Edit</a>
                    @endcan
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">Status</div><div>{{ $lead->status?->name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Source</div><div>{{ $lead->source?->name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Owner</div><div>{{ $lead->owner?->name ?? 'Unassigned' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Priority</div><div>{{ ucfirst($lead->priority) }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Email</div><div>{{ $lead->email ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Phone</div><div>{{ $lead->phone ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Next Follow-up</div><div>{{ $lead->next_follow_up_at?->format('Y-m-d H:i') ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Created By</div><div>{{ $lead->createdBy?->name ?? '-' }}</div></div>
                <div class="col-12">
                    <div class="text-muted small">Notes / Remarks</div>
                    <div class="border rounded p-3 bg-light">{{ $lead->notes ?: 'No notes recorded.' }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h2 class="h5 mb-0">Qualification</h2>
                @if ($lead->qualified_at)
                    <span class="badge text-bg-success rounded-1">Qualified</span>
                @endif
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="text-muted small">Outcome</div><div>{{ $lead->qualification_status ? ucfirst($lead->qualification_status) : '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Budget</div><div>{{ $lead->qualification_budget ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Authority</div><div>{{ $lead->qualification_authority ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Timeline</div><div>{{ $lead->qualification_timeline ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Interest</div><div>{{ $lead->qualification_interest_level ? ucfirst($lead->qualification_interest_level) : '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Qualified At</div><div>{{ $lead->qualified_at?->format('Y-m-d H:i') ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Qualified By</div><div>{{ $lead->qualifiedBy?->name ?? '-' }}</div></div>
                <div class="col-md-6"><div class="text-muted small">Need / Requirement</div><div class="border rounded p-3 bg-light">{{ $lead->qualification_need ?: 'No requirement recorded.' }}</div></div>
                <div class="col-md-6"><div class="text-muted small">Qualification Notes</div><div class="border rounded p-3 bg-light">{{ $lead->qualification_notes ?: 'No qualification notes recorded.' }}</div></div>
            </div>

            @can('leads.qualify')
                @unless ($lead->converted_at)
                    <form class="row g-3" method="post" action="{{ route('leads.qualify', $lead) }}">
                        @csrf
                        @method('patch')
                        <div class="col-md-3">
                            <label class="form-label" for="qualification_status">Outcome</label>
                            <select class="form-select" id="qualification_status" name="qualification_status" required>
                                @foreach (['working', 'qualified', 'unqualified', 'disqualified'] as $status)
                                    <option value="{{ $status }}" @selected(old('qualification_status', $lead->qualification_status ?: 'working') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="qualification_budget">Budget</label>
                            <input class="form-control" id="qualification_budget" type="number" step="0.01" min="0" name="qualification_budget" value="{{ old('qualification_budget', $lead->qualification_budget) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="qualification_authority">Authority</label>
                            <input class="form-control" id="qualification_authority" name="qualification_authority" value="{{ old('qualification_authority', $lead->qualification_authority) }}" maxlength="255">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="qualification_timeline">Timeline</label>
                            <input class="form-control" id="qualification_timeline" name="qualification_timeline" value="{{ old('qualification_timeline', $lead->qualification_timeline) }}" maxlength="255">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="qualification_interest_level">Interest</label>
                            <select class="form-select" id="qualification_interest_level" name="qualification_interest_level">
                                <option value="">Choose</option>
                                @foreach (['low', 'medium', 'high'] as $level)
                                    <option value="{{ $level }}" @selected(old('qualification_interest_level', $lead->qualification_interest_level) === $level)>{{ ucfirst($level) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label" for="qualification_need">Need / Requirement</label>
                            <input class="form-control" id="qualification_need" name="qualification_need" value="{{ old('qualification_need', $lead->qualification_need) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="qualification_notes">Qualification Notes</label>
                            <textarea class="form-control" id="qualification_notes" name="qualification_notes" rows="3">{{ old('qualification_notes', $lead->qualification_notes) }}</textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Save Qualification</button>
                        </div>
                    </form>
                @endunless
            @endcan
        </div>
    </section>

    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Conversion</h2>

            @if ($lead->converted_at)
                <div class="alert alert-success">Converted on {{ $lead->converted_at->format('Y-m-d H:i') }} by {{ $lead->convertedBy?->name ?? '-' }}.</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Customer</div>
                        <div>
                            @if ($lead->convertedCustomer)
                                <a href="{{ route('customers.show', $lead->convertedCustomer) }}">{{ $lead->convertedCustomer->name }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Primary Contact</div>
                        <div>
                            @if ($lead->convertedContact)
                                <a href="{{ route('contacts.show', $lead->convertedContact) }}">{{ $lead->convertedContact->name }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Opportunity</div>
                        <div>
                            @if ($lead->convertedOpportunity)
                                <a href="{{ route('opportunities.show', $lead->convertedOpportunity) }}">{{ $lead->convertedOpportunity->name }}</a>
                            @else
                                -
                            @endif
                        </div>
                    </div>
                </div>
            @else
                @can('leads.convert')
                    <form class="row g-3" method="post" action="{{ route('leads.convert', $lead) }}">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label" for="customer_name">Customer / Account Name</label>
                            <input class="form-control" id="customer_name" name="customer_name" value="{{ old('customer_name', $lead->company ?: $lead->name) }}" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_company">Company</label>
                            <input class="form-control" id="customer_company" name="customer_company" value="{{ old('customer_company', $lead->company) }}" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_email">Customer Email</label>
                            <input class="form-control" id="customer_email" type="email" name="customer_email" value="{{ old('customer_email', $lead->email) }}" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="customer_phone">Customer Phone</label>
                            <input class="form-control" id="customer_phone" name="customer_phone" value="{{ old('customer_phone', $lead->phone) }}" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact_first_name">Contact First Name</label>
                            <input class="form-control" id="contact_first_name" name="contact_first_name" value="{{ old('contact_first_name', \Illuminate\Support\Str::before($lead->name, ' ')) }}" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact_last_name">Contact Last Name</label>
                            <input class="form-control" id="contact_last_name" name="contact_last_name" value="{{ old('contact_last_name', \Illuminate\Support\Str::contains($lead->name, ' ') ? \Illuminate\Support\Str::after($lead->name, ' ') : '') }}" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact_email">Contact Email</label>
                            <input class="form-control" id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $lead->email) }}" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="contact_phone">Contact Phone</label>
                            <input class="form-control" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $lead->phone) }}" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="opportunity_name">Opportunity Name</label>
                            <input class="form-control" id="opportunity_name" name="opportunity_name" value="{{ old('opportunity_name', ($lead->company ?: $lead->name) . ' Opportunity') }}" required maxlength="255">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="opportunity_amount">Amount</label>
                            <input class="form-control" id="opportunity_amount" type="number" step="0.01" min="0" name="opportunity_amount" value="{{ old('opportunity_amount', $lead->qualification_budget) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="expected_close_date">Expected Close</label>
                            <input class="form-control" id="expected_close_date" type="date" name="expected_close_date" value="{{ old('expected_close_date') }}">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-success" type="submit" @disabled(! $lead->isQualifiedForConversion())>Convert Lead</button>
                        </div>
                    </form>
                @else
                    <div class="text-muted">You do not have permission to convert this lead.</div>
                @endcan
            @endif
        </div>
    </section>

    @can('leads.assign')
        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Assign Lead</h2>
                <form class="row g-3" method="post" action="{{ route('leads.assign', $lead) }}">
                    @csrf
                    @if ($errors->any())
                        <div class="col-12">
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        </div>
                    @endif
                    <div class="col-md-4">
                        <label class="form-label" for="assignment_method">Assignment Method</label>
                        <select class="form-select" id="assignment_method" name="assignment_method" required>
                            <option value="manual">Manual user assignment</option>
                            <option value="team">Team assignment</option>
                            <option value="round_robin">Round-robin team assignment</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="owner_id">User</label>
                        <select class="form-select" id="owner_id" name="owner_id">
                            <option value="">Choose user</option>
                            @foreach ($assignableOwners as $owner)
                                <option value="{{ $owner->id }}" @selected($lead->owner_id === $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="team_id">Team</label>
                        <select class="form-select" id="team_id" name="team_id">
                            <option value="">Choose team</option>
                            @foreach ($assignableTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Assign Lead</button>
                    </div>
                </form>
            </div>
        </section>
    @endcan

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h2 class="h5 mb-0">Activity Timeline</h2>
                        @can('activities.create')
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('activities.create', ['lead_id' => $lead->id]) }}">Add Activity</a>
                        @endcan
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>Activity</th>
                                <th>Type</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Due</th>
                                <th class="text-end">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse ($lead->activities->take(10) as $activity)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $activity->subject }}</div>
                                        <small class="text-muted">{{ ucfirst($activity->priority) }}</small>
                                    </td>
                                    <td>{{ $activity->type?->name ?? '-' }}</td>
                                    <td>{{ $activity->assignedUser?->name ?? '-' }}</td>
                                    <td>{{ ucfirst($activity->status) }}</td>
                                    <td class="{{ $activity->is_overdue ? 'text-danger fw-semibold' : '' }}">{{ $activity->due_at?->format('Y-m-d H:i') }}</td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('activities.show', $activity) }}">View</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-muted" colspan="6">No activities recorded.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Follow-ups</h2>
                    @php
                        $pendingActivities = $lead->activities->where('status', 'pending');
                        $overdueActivities = $pendingActivities->filter(fn ($activity) => $activity->is_overdue);
                        $upcomingActivities = $pendingActivities->reject(fn ($activity) => $activity->is_overdue)->sortBy('due_at')->take(5);
                    @endphp
                    <div class="mb-3">
                        <div class="fw-semibold text-danger mb-2">Overdue</div>
                        @forelse ($overdueActivities->take(5) as $activity)
                            <div class="border-bottom pb-2 mb-2">
                                <a href="{{ route('activities.show', $activity) }}">{{ $activity->subject }}</a>
                                <div class="small text-muted">{{ $activity->due_at?->format('Y-m-d H:i') }}</div>
                            </div>
                        @empty
                            <div class="text-muted small">No overdue follow-ups.</div>
                        @endforelse
                    </div>
                    <div>
                        <div class="fw-semibold mb-2">Upcoming</div>
                        @forelse ($upcomingActivities as $activity)
                            <div class="border-bottom pb-2 mb-2">
                                <a href="{{ route('activities.show', $activity) }}">{{ $activity->subject }}</a>
                                <div class="small text-muted">{{ $activity->due_at?->format('Y-m-d H:i') }}</div>
                            </div>
                        @empty
                            <div class="text-muted small">No upcoming follow-ups.</div>
                        @endforelse
                    </div>
                </div>
            </section>

            @can('activities.create')
                <section class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h2 class="h5 mb-3">Quick Add Activity</h2>
                        <form class="row g-3" method="post" action="{{ route('activities.store') }}">
                            @csrf
                            <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                            <input type="hidden" name="assigned_user_id" value="{{ $lead->owner_id ?: auth()->id() }}">
                            <input type="hidden" name="priority" value="normal">
                            <input type="hidden" name="status" value="pending">
                            <div class="col-12">
                                <label class="form-label" for="activity_type_id">Type</label>
                                <select class="form-select" id="activity_type_id" name="activity_type_id" required>
                                    @foreach ($activityTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="subject">Subject</label>
                                <input class="form-control" id="subject" name="subject" required maxlength="255">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="due_at">Due Date / Time</label>
                                <input class="form-control" id="due_at" type="datetime-local" name="due_at" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="description">Notes</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit">Add Activity</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endcan
        </div>
    </div>

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

    <section class="card border-0 shadow-sm mt-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Assignment History</h2>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Assigned At</th>
                        <th>Method</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Team</th>
                        <th>By</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($lead->assignmentHistories as $history)
                        <tr>
                            <td>{{ $history->assigned_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $history->method)) }}</td>
                            <td>{{ $history->assignedFrom?->name ?? 'Unassigned' }}</td>
                            <td>{{ $history->assignedTo?->name ?? 'Unassigned' }}</td>
                            <td>{{ $history->team?->name ?? '-' }}</td>
                            <td>{{ $history->assignedBy?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted" colspan="6">No assignment changes yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
