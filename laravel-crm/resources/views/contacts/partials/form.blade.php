@csrf
@php
    $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="customer_id">Customer / Account</label>
        <select class="form-select" id="customer_id" name="customer_id" required>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $contact->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="title">Title</label>
        <input class="form-control" id="title" name="title" value="{{ old('title', $contact->title) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="first_name">First Name</label>
        <input class="form-control" id="first_name" name="first_name" value="{{ old('first_name', $contact->first_name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="last_name">Last Name</label>
        <input class="form-control" id="last_name" name="last_name" value="{{ old('last_name', $contact->last_name) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $contact->email) }}" maxlength="255">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="phone">Phone</label>
        <input class="form-control" id="phone" name="phone" value="{{ old('phone', $phonePrivacy->editableValue($contact->phone, auth()->user())) }}" placeholder="{{ $phonePrivacy->editablePlaceholder($contact->phone, auth()->user()) }}" maxlength="50">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="mobile">Mobile</label>
        <input class="form-control" id="mobile" name="mobile" value="{{ old('mobile', $phonePrivacy->editableValue($contact->mobile, auth()->user())) }}" placeholder="{{ $phonePrivacy->editablePlaceholder($contact->mobile, auth()->user()) }}" maxlength="50">
    </div>
    <div class="col-md-6">
        <div class="form-check">
            <input type="hidden" name="is_primary" value="0">
            <input class="form-check-input" id="is_primary" type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $contact->is_primary ?? false))>
            <label class="form-check-label" for="is_primary">Primary contact</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" id="is_active" type="checkbox" name="is_active" value="1" @checked(old('is_active', $contact->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $contact->notes) }}</textarea>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('contacts.index') }}">Cancel</a>
</div>
