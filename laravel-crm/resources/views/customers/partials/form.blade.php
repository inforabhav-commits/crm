@csrf
@php
    $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Customer / Account Name</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $customer->name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="company">Company</label>
        <input class="form-control" id="company" name="company" value="{{ old('company', $customer->company) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone</label>
        <input class="form-control" id="phone" name="phone" value="{{ old('phone', $phonePrivacy->editableValue($customer->phone, auth()->user())) }}" placeholder="{{ $phonePrivacy->editablePlaceholder($customer->phone, auth()->user()) }}" maxlength="50">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="website">Website</label>
        <input class="form-control" id="website" name="website" value="{{ old('website', $customer->website) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="industry">Industry</label>
        <input class="form-control" id="industry" name="industry" value="{{ old('industry', $customer->industry) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="owner_id">Owner</label>
        <select class="form-select" id="owner_id" name="owner_id" required>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((string) old('owner_id', $customer->owner_id ?: auth()->id()) === (string) $owner->id)>{{ $owner->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="converted_from_lead_id">Converted From Lead</label>
        <select class="form-select" id="converted_from_lead_id" name="converted_from_lead_id">
            <option value="">None</option>
            @foreach ($leads as $lead)
                <option value="{{ $lead->id }}" @selected((string) old('converted_from_lead_id', $customer->converted_from_lead_id) === (string) $lead->id)>{{ $lead->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label" for="address">Address</label>
        <textarea class="form-control" id="address" name="address" rows="3">{{ old('address', $customer->address) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $customer->notes) }}</textarea>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('customers.index') }}">Cancel</a>
</div>
