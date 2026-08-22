@extends('layouts.crm', ['title' => 'Pipeline'])

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex align-items-center justify-content-between">
            <div>
                <h2 class="h5 mb-1">Kanban Pipeline</h2>
                <p class="text-muted mb-0">Opportunities grouped by configured CRM opportunity stages.</p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('opportunities.index') }}">List</a>
        </div>
    </section>

    <div class="row g-3">
        @foreach ($stages as $stage)
            @php
                $stageOpportunities = $opportunitiesByStage->get($stage->id, collect());
            @endphp
            <div class="col-lg-3 col-md-6">
                <section class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h2 class="h6 mb-0">{{ $stage->name }}</h2>
                            <span class="badge text-bg-secondary rounded-1">{{ $stageOpportunities->count() }}</span>
                        </div>

                        @forelse ($stageOpportunities as $opportunity)
                            <div class="border rounded p-3 mb-3 bg-light">
                                <div class="fw-semibold"><a href="{{ route('opportunities.show', $opportunity) }}">{{ $opportunity->name }}</a></div>
                                <div class="small text-muted">{{ $opportunity->customer?->name ?? '-' }}</div>
                                <div class="small">{{ $opportunity->currency }} {{ $opportunity->amount ?? '0.00' }}</div>
                                <div class="small text-muted">{{ $opportunity->owner?->name ?? '-' }} - {{ $opportunity->expected_close_date?->format('Y-m-d') ?? 'No close date' }}</div>
                                @can('opportunities.change_stage')
                                    <form class="mt-2" method="post" action="{{ route('opportunities.change-stage', $opportunity) }}">
                                        @csrf
                                        @method('patch')
                                        <select class="form-select form-select-sm mb-2" name="stage_id" required>
                                            @foreach ($stages as $targetStage)
                                                <option value="{{ $targetStage->id }}" @selected($targetStage->id === $opportunity->stage_id)>{{ $targetStage->name }}</option>
                                            @endforeach
                                        </select>
                                        <select class="form-select form-select-sm mb-2" name="loss_reason_id">
                                            <option value="">Loss reason</option>
                                            @foreach ($lossReasons as $reason)
                                                <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Move</button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <div class="text-muted small">No deals in this stage.</div>
                        @endforelse
                    </div>
                </section>
            </div>
        @endforeach
    </div>
@endsection
