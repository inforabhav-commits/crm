@extends('layouts.crm', ['title' => 'Dashboard'])

@section('content')
    <section class="card mb-4">
        <div class="card-body">
            <form class="row g-2" method="get" action="{{ route('dashboard') }}">
                <div class="col-md-3">
                    <label class="form-label" for="owner">Owner</label>
                    <select class="form-select" id="owner" name="owner">
                        <option value="">All permitted owners</option>
                        @foreach ($owners as $owner)
                            <option value="{{ $owner->id }}" @selected((string) ($filters['owner'] ?? '') === (string) $owner->id)>{{ $owner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="team">Team</label>
                    <select class="form-select" id="team" name="team">
                        <option value="">All permitted teams</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected((string) ($filters['team'] ?? '') === (string) $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date">Date</label>
                    <input class="form-control" id="date" type="date" name="date" value="{{ ($filters['date'] ?? '') ?: $selectedDate->format('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="activity_status">Activity Status</label>
                    <select class="form-select" id="activity_status" name="activity_status">
                        <option value="">Any status</option>
                        @foreach ($activityStatuses as $status)
                            <option value="{{ $status }}" @selected(($filters['activity_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 d-grid align-items-end">
                    <button class="btn btn-outline-primary" type="submit">Apply</button>
                </div>
            </form>
        </div>
    </section>

    <div class="row g-3">
        @foreach ([
            ['label' => 'My Leads', 'value' => $kpis['my_leads']],
            ['label' => 'New Leads', 'value' => $kpis['new_leads']],
            ['label' => 'Leads Requiring Follow-up', 'value' => $kpis['leads_requiring_follow_up']],
            ['label' => "Today's Activities", 'value' => $kpis['today_activities']],
            ['label' => 'Overdue Activities', 'value' => $kpis['overdue_activities']],
            ['label' => 'Upcoming Follow-ups', 'value' => $kpis['upcoming_follow_ups']],
            ['label' => 'Open Opportunities', 'value' => $kpis['open_opportunities']],
            ['label' => 'Pipeline Value', 'value' => number_format((float) $kpis['pipeline_value'], 2)],
        ] as $card)
            <div class="col-xl-3 col-md-6">
                <section class="card crm-kpi-card h-100">
                    <div class="card-body">
                        <span class="crm-kpi-accent" aria-hidden="true"></span>
                        <div class="crm-kpi-label">{{ $card['label'] }}</div>
                        <div class="crm-kpi-value mt-2">{{ $card['value'] }}</div>
                    </div>
                </section>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-7">
            <section class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Daily Work Queue</h2>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Priority</th><th>Item</th><th>Owner</th><th>Due</th><th class="text-end">Open</th></tr></thead>
                            <tbody>
                            @forelse ($workQueue as $item)
                                <tr>
                                    <td><span class="badge text-bg-secondary rounded-1">{{ $item['label'] }}</span></td>
                                    <td>
                                        <div class="fw-semibold">{{ $item['title'] }}</div>
                                        <small class="text-muted">{{ ucfirst($item['priority'] ?? 'normal') }}</small>
                                    </td>
                                    <td>{{ $item['owner'] ?: '-' }}</td>
                                    <td>{{ $item['due_at'] ? \Illuminate\Support\Carbon::parse($item['due_at'])->format('Y-m-d H:i') : '-' }}</td>
                                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ $item['url'] }}">Open</a></td>
                                </tr>
                            @empty
                                <tr><td class="text-muted" colspan="5">No work queue items.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Recently Assigned Leads</h2>
                    @forelse ($recentlyAssignedLeads as $history)
                        <div class="border-bottom pb-2 mb-2">
                            <a class="fw-semibold" href="{{ route('leads.show', $history->lead) }}">{{ $history->lead?->name }}</a>
                            <div class="small text-muted">To {{ $history->assignedTo?->name ?? 'Unassigned' }} by {{ $history->assignedBy?->name ?? '-' }} - {{ $history->assigned_at?->format('Y-m-d H:i') }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No recent assignments.</div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Open Opportunities</h2>
                    @forelse ($openOpportunities as $opportunity)
                        <div class="border-bottom pb-2 mb-2">
                            <a class="fw-semibold" href="{{ route('opportunities.show', $opportunity) }}">{{ $opportunity->name }}</a>
                            <div class="small text-muted">{{ $opportunity->customer?->name ?? '-' }} - {{ $opportunity->currency }} {{ $opportunity->amount ?? '0.00' }} - {{ $opportunity->expected_close_date?->format('Y-m-d') ?? 'No close date' }}</div>
                        </div>
                    @empty
                        <div class="text-muted">No open opportunities.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-4">
            <section class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Today's Activities</h2>
                    @include('partials.dashboard-activity-list', ['activities' => $todayActivities])
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Overdue Activities</h2>
                    @include('partials.dashboard-activity-list', ['activities' => $overdueActivities])
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <section class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Upcoming Follow-ups</h2>
                    @include('partials.dashboard-activity-list', ['activities' => $upcomingActivities])
                </div>
            </section>
        </div>
    </div>

    <section class="card mt-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Recent CRM Activity</h2>
            @include('partials.dashboard-activity-list', ['activities' => $recentCrmActivity])
        </div>
    </section>
@endsection
