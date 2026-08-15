@extends('layouts.crm', ['title' => 'Calls'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <form class="row g-3 mb-4" method="get">
                <div class="col-md-2">
                    <label class="form-label" for="direction">Direction</label>
                    <select class="form-select" id="direction" name="direction">
                        <option value="">All</option>
                        @foreach ($directions as $direction)
                            <option value="{{ $direction }}" @selected(($filters['direction'] ?? '') === $direction)>{{ ucfirst($direction) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="agent">Agent</label>
                    <select class="form-select" id="agent" name="agent">
                        <option value="">All</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((string) ($filters['agent'] ?? '') === (string) $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date">Date</label>
                    <input class="form-control" id="date" type="date" name="date" value="{{ $filters['date'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="match">Match</label>
                    <select class="form-select" id="match" name="match">
                        <option value="">All</option>
                        <option value="matched" @selected(($filters['match'] ?? '') === 'matched')>Matched</option>
                        <option value="unmatched" @selected(($filters['match'] ?? '') === 'unmatched')>Unmatched</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                </div>
            </form>

            @include('call_logs._table', ['callLogs' => $callLogs])

            {{ $callLogs->links() }}
        </div>
    </section>
@endsection
