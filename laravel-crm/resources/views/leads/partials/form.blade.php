@csrf
@php
    $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Lead Name</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $lead->name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="company">Company</label>
        <input class="form-control" id="company" name="company" value="{{ old('company', $lead->company) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $lead->email) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone</label>
        <input class="form-control" id="phone" name="phone" value="{{ old('phone', $phonePrivacy->editableValue($lead->phone, auth()->user())) }}" placeholder="{{ $phonePrivacy->editablePlaceholder($lead->phone, auth()->user()) }}" maxlength="50">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="lead_status_id">Status</label>
        <select class="form-select" id="lead_status_id" name="lead_status_id" required>
            @foreach ($statuses as $status)
                <option value="{{ $status->id }}" @selected((string) old('lead_status_id', $lead->lead_status_id) === (string) $status->id)>{{ $status->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="lead_source_id">Source</label>
        <select class="form-select" id="lead_source_id" name="lead_source_id">
            <option value="">None</option>
            @foreach ($sources as $source)
                <option value="{{ $source->id }}" @selected((string) old('lead_source_id', $lead->lead_source_id) === (string) $source->id)>{{ $source->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="owner_id">Owner / Agent</label>
        <select class="form-select" id="owner_id" name="owner_id">
            <option value="">Unassigned</option>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((string) old('owner_id', $lead->owner_id ?: auth()->id()) === (string) $owner->id)>{{ $owner->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="priority">Priority</label>
        <select class="form-select" id="priority" name="priority" required>
            @foreach ($priorities as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $lead->priority ?: 'normal') === $priority)>{{ ucfirst($priority) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="next_follow_up_at">Next Follow-up</label>
        <input class="form-control" id="next_follow_up_at" type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at', $lead->next_follow_up_at?->format('Y-m-d\\TH:i')) }}">
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">Notes / Remarks</label>
        <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $lead->notes) }}</textarea>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('leads.index') }}">Cancel</a>
</div>
