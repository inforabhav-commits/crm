@extends('layouts.crm', ['title' => 'JustCall Reconciliation'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h5 mb-0">JustCall Reconciliation</h2>
            <div class="text-muted small">Review unmatched or problematic call exceptions and resolve them safely.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.justcall-monitoring.index') }}">Back to Monitoring</a>
    </div>

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h3 class="h6">Filters</h3>
            <form class="row g-2" method="get">
                <div class="col-md-3">
                    <label class="form-label" for="direction">Direction</label>
                    <select class="form-select" id="direction" name="direction">
                        <option value="">All</option>
                        @foreach (['inbound', 'outbound', 'unknown'] as $direction)
                            <option value="{{ $direction }}" @selected(($filters['direction'] ?? '') === $direction)>{{ ucfirst($direction) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        @foreach (['missed', 'completed', 'answered', 'ringing', 'initiated'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-sm" type="submit">Apply</button>
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.justcall-monitoring.reconcile') }}">Reset</a>
                </div>
            </form>
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Call</th><th>Phone</th><th>Direction</th><th>Status</th><th>Mapped Agent</th><th>Review</th><th class="text-end">Action</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($calls as $callLog)
                            <tr>
                                <td>{{ $callLog->external_call_id ?: $callLog->id }}</td>
                                <td>{{ $callLog->customer_number ?: $callLog->from_number ?: '-' }}</td>
                                <td>{{ $callLog->direction ?: '-' }}</td>
                                <td>{{ $callLog->status ?: '-' }}</td>
                                <td>{{ $callLog->user?->name ?? $callLog->agent_external_id ?: '-' }}</td>
                                <td>{{ $callLog->review_status ?: 'pending' }}</td>
                                <td class="text-end">
                                    <form class="row g-2" method="post" action="{{ route('admin.justcall-monitoring.reconcile.store') }}">
                                        @csrf
                                        <input type="hidden" name="call_log_id" value="{{ $callLog->id }}">
                                        <div class="col-md-4">
                                            <select class="form-select form-select-sm" name="related_type">
                                                <option value="">Link record</option>
                                                @foreach ($relatedOptions as $type => $label)
                                                    <option value="{{ $type }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input class="form-control form-control-sm" type="number" name="related_id" placeholder="Record ID">
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select form-select-sm" name="assign_agent_id">
                                                <option value="">Assign agent</option>
                                                @foreach ($agents as $agent)
                                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-primary btn-sm" type="submit">Resolve</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No exceptions require reconciliation.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $calls->links() }}
        </div>
    </section>
@endsection
