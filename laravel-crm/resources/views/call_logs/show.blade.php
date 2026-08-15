@extends('layouts.crm', ['title' => 'Call Detail'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ ucfirst($callLog->direction) }} Call</h2>
                    <p class="text-muted mb-0">{{ $callLog->occurredAt()?->format('Y-m-d H:i') ?? 'No call time recorded' }}</p>
                </div>
                <a class="btn btn-outline-secondary" href="{{ route('calls.index') }}">Back</a>
            </div>

            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">Status</div><div>{{ $callLog->status ? ucfirst($callLog->status) : '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Agent</div><div>{{ $callLog->user?->name ?? ($callLog->agent_external_id ?: '-') }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Phone</div><div>{{ $callLog->customer_number ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Duration</div><div>{{ $callLog->duration_seconds !== null ? gmdate('H:i:s', $callLog->duration_seconds) : '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Provider Disposition</div><div>{{ $callLog->provider_disposition ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">CRM Disposition</div><div>{{ $callLog->crm_disposition ?: '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Follow-up Required</div><div>{{ $callLog->follow_up_required ? 'Yes' : 'No' }}</div></div>
                <div class="col-md-3">
                    <div class="text-muted small">Recording</div>
                    <div>
                        @if ($callLog->hasAvailableRecording())
                            <span class="badge text-bg-success rounded-1">Available</span>
                        @else
                            <span class="badge text-bg-secondary rounded-1">Unavailable</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Follow-up Activity</div>
                    <div>
                        @if ($callLog->followUpActivity)
                            <a href="{{ route('activities.show', $callLog->followUpActivity) }}">{{ $callLog->followUpActivity->subject }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-md-6"><div class="text-muted small">Provider Notes</div><div class="border rounded p-3 bg-light">{{ $callLog->provider_notes ?: 'No provider notes.' }}</div></div>
                <div class="col-md-6"><div class="text-muted small">CRM Notes</div><div class="border rounded p-3 bg-light">{{ $callLog->crm_notes ?: 'No CRM notes.' }}</div></div>
            </div>

            <div class="mt-3">
                @can('calls.recordings.view')
                    @if ($callLog->hasAvailableRecording())
                        <a class="btn btn-outline-primary" href="{{ route('calls.recording', $callLog) }}">Access Recording</a>
                    @else
                        <span class="text-muted">Recording is not available yet.</span>
                    @endif
                @endcan
            </div>
        </div>
    </section>

    @can('calls.update')
        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Update Outcome</h2>
                <form class="row g-3" method="post" action="{{ route('calls.update', $callLog) }}">
                    @csrf
                    @method('patch')
                    <div class="col-md-4">
                        <label class="form-label" for="crm_disposition">Disposition</label>
                        <select class="form-select" id="crm_disposition" name="crm_disposition">
                            <option value="">Use provider value</option>
                            @foreach ($dispositions as $disposition)
                                <option value="{{ $disposition }}" @selected(old('crm_disposition', $callLog->crm_disposition) === $disposition)>{{ $disposition }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="next_follow_up_at">Next Follow-up</label>
                        <input class="form-control" id="next_follow_up_at" type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at', $callLog->followUpActivity?->due_at?->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" id="follow_up_required" type="checkbox" name="follow_up_required" value="1" @checked(old('follow_up_required', $callLog->follow_up_required))>
                            <label class="form-check-label" for="follow_up_required">Follow-up required</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="crm_notes">Call Notes</label>
                        <textarea class="form-control" id="crm_notes" name="crm_notes" rows="5" maxlength="5000">{{ old('crm_notes', $callLog->crm_notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save Call Notes</button>
                    </div>
                </form>
            </div>
        </section>
    @endcan
@endsection
