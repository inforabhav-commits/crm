@extends('layouts.crm', ['title' => 'Calls'])

@section('content')
    @php
        $metricQuery = \App\Models\CallLog::visibleTo(auth()->user());

        if (! empty($filters['direction'])) {
            $metricQuery->where('direction', $filters['direction']);
        }

        if (! empty($filters['status'])) {
            $metricQuery->where('status', $filters['status']);
        }

        if (! empty($filters['agent'])) {
            $metricQuery->where('user_id', $filters['agent']);
        }

        if (! empty($filters['date'])) {
            $metricQuery->whereDate('last_event_at', $filters['date']);
        }

        if (($filters['match'] ?? '') === 'matched') {
            $metricQuery->where(function ($matchQuery) {
                $matchQuery->whereNotNull('lead_id')
                    ->orWhereNotNull('customer_id')
                    ->orWhereNotNull('contact_id')
                    ->orWhereNotNull('opportunity_id');
            });
        } elseif (($filters['match'] ?? '') === 'unmatched') {
            $metricQuery->whereNull('lead_id')
                ->whereNull('customer_id')
                ->whereNull('contact_id')
                ->whereNull('opportunity_id');
        }

        $callMetrics = [
            ['label' => 'Total Calls', 'value' => (clone $metricQuery)->count(), 'tone' => 'primary', 'icon' => 'phone'],
            ['label' => 'Incoming', 'value' => (clone $metricQuery)->where('direction', 'inbound')->count(), 'tone' => 'info', 'icon' => 'phone-incoming'],
            ['label' => 'Outgoing', 'value' => (clone $metricQuery)->where('direction', 'outbound')->count(), 'tone' => 'primary', 'icon' => 'phone-outgoing'],
            ['label' => 'Missed', 'value' => (clone $metricQuery)->where('status', 'missed')->count(), 'tone' => 'danger', 'icon' => 'phone-missed'],
            ['label' => 'Completed', 'value' => (clone $metricQuery)->where('status', 'completed')->count(), 'tone' => 'success', 'icon' => 'check-circle'],
        ];
    @endphp

    <div class="crm-page-intro d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h4 mb-1 crm-page-title">Calls</h2>
            <p class="crm-page-subtitle mb-0">Manage and review customer call activity</p>
        </div>
        @can('export.crm')
            <a class="btn btn-outline-primary btn-sm" href="{{ route('import-export.export', 'calls') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"><i data-lucide="download" aria-hidden="true"></i><span>Export CSV</span></a>
        @endcan
    </div>

    <section class="crm-kpi-grid mb-3">
        @foreach ($callMetrics as $metric)
            <div class="card crm-kpi-card crm-kpi-card-{{ $metric['tone'] }}">
                    <div class="card-body">
                        <div class="crm-kpi-accent"></div>
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="crm-kpi-label">{{ $metric['label'] }}</div>
                                <div class="crm-kpi-value">{{ number_format($metric['value']) }}</div>
                            </div>
                            <span class="crm-kpi-icon" aria-hidden="true"><i data-lucide="{{ $metric['icon'] }}"></i></span>
                        </div>
                    </div>
                </div>
        @endforeach
    </section>

    <section class="card crm-filter-card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h3 class="crm-section-heading mb-0">Filters</h3>
                @if (collect($filters)->filter()->isNotEmpty())
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('calls.index') }}">Reset</a>
                @endif
            </div>
            <form class="row g-2 align-items-end" method="get">
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="direction">Direction</label>
                    <select class="form-select" id="direction" name="direction">
                        <option value="">All</option>
                        @foreach ($directions as $direction)
                            <option value="{{ $direction }}" @selected(($filters['direction'] ?? '') === $direction)>{{ ucfirst($direction) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="agent">Agent</label>
                    <select class="form-select" id="agent" name="agent">
                        <option value="">All</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((string) ($filters['agent'] ?? '') === (string) $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="date">Date</label>
                    <input class="form-control" id="date" type="date" name="date" value="{{ $filters['date'] ?? '' }}">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="match">Match</label>
                    <select class="form-select" id="match" name="match">
                        <option value="">All</option>
                        <option value="matched" @selected(($filters['match'] ?? '') === 'matched')>Matched</option>
                        <option value="unmatched" @selected(($filters['match'] ?? '') === 'unmatched')>Unmatched</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-1 d-grid">
                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card crm-table-card">
        <div class="card-body">
            @include('call_logs._table', ['callLogs' => $callLogs])

            <div class="crm-pagination mt-3">
                {{ $callLogs->links() }}
            </div>
        </div>
    </section>
@endsection
