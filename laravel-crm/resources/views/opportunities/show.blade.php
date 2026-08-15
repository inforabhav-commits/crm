@extends('layouts.crm', ['title' => 'Opportunity'])

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
                    <h2 class="h5 mb-1">{{ $opportunity->name }}</h2>
                    <p class="text-muted mb-0">{{ $opportunity->stage?->name ?? 'Opportunity' }} - {{ ucfirst($opportunity->status) }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('opportunities.index') }}">Back</a>
                    <a class="btn btn-outline-primary" href="{{ route('opportunities.pipeline') }}">Pipeline</a>
                    @can('activities.create')
                        <a class="btn btn-outline-primary" href="{{ route('activities.create', ['opportunity_id' => $opportunity->id]) }}">Add Activity</a>
                    @endcan
                    @can('opportunities.edit')
                        <a class="btn btn-primary" href="{{ route('opportunities.edit', $opportunity) }}">Edit</a>
                    @endcan
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">Customer</div><div><a href="{{ route('customers.show', $opportunity->customer) }}">{{ $opportunity->customer?->name }}</a></div></div>
                <div class="col-md-3"><div class="text-muted small">Contact</div><div>{{ $opportunity->contact?->name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Owner</div><div>{{ $opportunity->owner?->name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Value</div><div>{{ $opportunity->currency }} {{ $opportunity->amount ?? '0.00' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Probability</div><div>{{ $opportunity->probability }}%</div></div>
                <div class="col-md-3"><div class="text-muted small">Expected Close</div><div>{{ $opportunity->expected_close_date?->format('Y-m-d') ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Closed At</div><div>{{ $opportunity->closed_at?->format('Y-m-d H:i') ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Loss Reason</div><div>{{ $opportunity->lossReason?->name ?? '-' }}</div></div>
                <div class="col-md-3">
                    <div class="text-muted small">Source Lead</div>
                    <div>
                        @if ($opportunity->sourceLead)
                            <a href="{{ route('leads.show', $opportunity->sourceLead) }}">{{ $opportunity->sourceLead->name }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-md-9"><div class="text-muted small">Next Step</div><div>{{ $opportunity->next_step ?: '-' }}</div></div>
                <div class="col-md-6"><div class="text-muted small">Description</div><div class="border rounded p-3 bg-light">{{ $opportunity->description ?: 'No description recorded.' }}</div></div>
                <div class="col-md-6"><div class="text-muted small">Notes</div><div class="border rounded p-3 bg-light">{{ $opportunity->notes ?: 'No notes recorded.' }}</div></div>
            </div>
        </div>
    </section>

    @can('opportunities.change_stage')
        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Change Stage</h2>
                <form class="row g-3" method="post" action="{{ route('opportunities.change-stage', $opportunity) }}">
                    @csrf
                    @method('patch')
                    <div class="col-md-4">
                        <label class="form-label" for="stage_id">Stage</label>
                        <select class="form-select" id="stage_id" name="stage_id" required>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage->id }}" @selected($opportunity->stage_id === $stage->id)>{{ $stage->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="loss_reason_id">Loss Reason</label>
                        <select class="form-select" id="loss_reason_id" name="loss_reason_id">
                            <option value="">None</option>
                            @foreach ($lossReasons as $reason)
                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="stage_notes">Notes</label>
                        <input class="form-control" id="stage_notes" name="stage_notes" maxlength="5000">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Update Stage</button>
                    </div>
                </form>
            </div>
        </section>
    @endcan

    <div class="row g-4 mt-1">
        <div class="col-lg-6">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Upcoming Follow-ups</h2>
                    @forelse ($upcomingActivities as $activity)
                        <div class="border-bottom pb-2 mb-2">
                            <a href="{{ route('activities.show', $activity) }}">{{ $activity->subject }}</a>
                            <div class="small text-muted">{{ $activity->due_at?->format('Y-m-d H:i') }} - {{ $activity->assignedUser?->name ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No upcoming activities.</div>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Overdue Activities</h2>
                    @forelse ($overdueActivities as $activity)
                        <div class="border-bottom pb-2 mb-2">
                            <a class="text-danger fw-semibold" href="{{ route('activities.show', $activity) }}">{{ $activity->subject }}</a>
                            <div class="small text-muted">{{ $activity->due_at?->format('Y-m-d H:i') }} - {{ $activity->assignedUser?->name ?? '-' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No overdue activities.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Activity Timeline</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Activity</th><th>Type</th><th>Owner</th><th>Status</th><th>Due</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                            @forelse ($recentActivities as $activity)
                                <tr>
                                    <td>{{ $activity->subject }}</td>
                                    <td>{{ $activity->type?->name ?? '-' }}</td>
                                    <td>{{ $activity->assignedUser?->name ?? '-' }}</td>
                                    <td>{{ ucfirst($activity->status) }}</td>
                                    <td>{{ $activity->due_at?->format('Y-m-d H:i') }}</td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('activities.show', $activity) }}">View</a></td>
                                </tr>
                            @empty
                                <tr><td class="text-muted" colspan="6">No activities recorded.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Stage History</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Changed</th><th>From</th><th>To</th><th>By</th></tr></thead>
                            <tbody>
                            @forelse ($opportunity->stageHistories as $history)
                                <tr>
                                    <td>{{ $history->changed_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $history->fromStage?->name ?? '-' }}</td>
                                    <td>{{ $history->toStage?->name ?? '-' }}</td>
                                    <td>{{ $history->changedBy?->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-muted" colspan="4">No stage changes recorded.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
