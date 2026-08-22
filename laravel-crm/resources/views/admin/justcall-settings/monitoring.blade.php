@extends('layouts.crm', ['title' => 'Integraton Monitoring'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-0">Integration Monitoring</h2>
            <div class="text-muted small">JustCall health, webhook backlog, and reconciliation exceptions.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.justcall-settings.index') }}">Settings</a>
            @can('justcall.manage')
                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.justcall-monitoring.reconcile') }}">Reconciliation</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($summary as $label => $value)
            @php
                $labelText = str_replace('_', ' ', $label);
            @endphp
            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <div class="text-muted small text-capitalize">
                        @if ($label === 'unmapped_agents')
                            Unmapped JustCall agents
                        @else
                            {{ str_replace('-', ' ', ucfirst(str_replace('_', ' ', $label))) }}
                        @endif
                    </div>
                    <div class="fw-semibold mt-1">{{ $value instanceof \Illuminate\Support\Carbon ? $value->format('Y-m-d H:i') : ($value ?? '0') }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($exceptionCalls->isNotEmpty())
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h3 class="h6">Exception queue</h3>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>Call</th><th>Issue</th><th>Phone</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($exceptionCalls as $call)
                                <tr>
                                    <td>{{ $call['id'] }}</td>
                                    <td>{{ $call['issue'] }}</td>
                                    <td>{{ $call['phone'] }}</td>
                                    <td>{{ $call['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h3 class="h6">Filters</h3>
            <form class="row g-2" method="get">
                <div class="col-md-3">
                    <label class="form-label" for="from">From</label>
                    <input class="form-control" id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="to">To</label>
                    <input class="form-control" id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="event_type">Event type</label>
                    <select class="form-select" id="event_type" name="event_type">
                        <option value="">All</option>
                        @foreach ($eventTypes as $eventType)
                            <option value="{{ $eventType }}" @selected(($filters['event_type'] ?? '') === $eventType)>{{ $eventType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="processing_status">Status</label>
                    <select class="form-select" id="processing_status" name="processing_status">
                        <option value="">All</option>
                        @foreach ($processingStatuses as $status)
                            <option value="{{ $status }}" @selected(($filters['processing_status'] ?? '') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-sm" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.justcall-monitoring.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h3 class="h6">Webhook inbox</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr><th>Received</th><th>Event</th><th>Status</th><th>Attempts</th><th>Summary</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ $entry->received_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $entry->event_type ?: '-' }}</td>
                                <td>{{ $entry->processing_status ?: '-' }}</td>
                                <td>{{ $entry->attempt_count }}</td>
                                <td>{{ $entry->failure_summary ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No webhook inbox entries.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $entries->links() }}
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h3 class="h6">Mapping health</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr><th>CRM User</th><th>Issue</th><th>Last Verified</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($mappingIssues as $mapping)
                            <tr>
                                <td>{{ $mapping->user?->name ?? 'Unknown user' }}</td>
                                <td>{{ ! $mapping->user || ! $mapping->user->is_active ? 'Inactive CRM user mapping' : 'Missing JustCall mapping' }}</td>
                                <td>{{ $mapping->last_verified_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No mapping issues detected.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
