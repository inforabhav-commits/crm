@csrf
@php
    $phonePrivacy = app(\App\Services\PhonePrivacyService::class);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="external_customer_id">Customer ID</label>
        <input class="form-control" id="external_customer_id" name="external_customer_id" value="{{ old('external_customer_id', $customer->external_customer_id) }}" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="sale_date">Date</label>
        <input class="form-control" id="sale_date" name="sale_date" value="{{ old('sale_date', $customer->sale_date) }}" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="end">End</label>
        <input class="form-control" id="end" name="end" value="{{ old('end', $customer->end) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="name">Customer / Account Name</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $customer->name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="company">Business Name</label>
        <input class="form-control" id="company" name="company" value="{{ old('company', $customer->company) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="phone">Phone No</label>
        <input class="form-control" id="phone" name="phone" value="{{ old('phone', $phonePrivacy->editableValue($customer->phone, auth()->user())) }}" placeholder="{{ $phonePrivacy->editablePlaceholder($customer->phone, auth()->user()) }}" maxlength="50">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="amount">Amount</label>
        <input class="form-control" id="amount" name="amount" value="{{ old('amount', $customer->amount) }}" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="plan">Plan</label>
        <input class="form-control" id="plan" name="plan" value="{{ old('plan', $customer->plan) }}" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="software">Software</label>
        <input class="form-control" id="software" name="software" value="{{ old('software', $customer->software) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="license_number">Liscense Number</label>
        <input class="form-control" id="license_number" name="license_number" value="{{ old('license_number', $customer->license_number) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="product_number">Product Number</label>
        <input class="form-control" id="product_number" name="product_number" value="{{ old('product_number', $customer->product_number) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="file_password">File Password</label>
        <input class="form-control" id="file_password" name="file_password" value="{{ old('file_password', $customer->file_password) }}" maxlength="5000">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="cloud_customer">Cloud Customer</label>
        <input class="form-control" id="cloud_customer" name="cloud_customer" value="{{ old('cloud_customer', $customer->cloud_customer) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="customer_user_id">User ID</label>
        <input class="form-control" id="customer_user_id" name="customer_user_id" value="{{ old('customer_user_id', $customer->customer_user_id) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="customer_password">Password</label>
        <input class="form-control" id="customer_password" name="customer_password" value="{{ old('customer_password', $customer->customer_password) }}" maxlength="5000">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="sale_type">Sale Type</label>
        <input class="form-control" id="sale_type" name="sale_type" value="{{ old('sale_type', $customer->sale_type) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="no_of_cases">No of Cases</label>
        <input class="form-control" id="no_of_cases" name="no_of_cases" value="{{ old('no_of_cases', $customer->no_of_cases) }}" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="payment_type">Payment Type</label>
        <input class="form-control" id="payment_type" name="payment_type" value="{{ old('payment_type', $customer->payment_type) }}" maxlength="255">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="last_4">Last 4</label>
        <input class="form-control" id="last_4" name="last_4" value="{{ old('last_4', $customer->last_4) }}" maxlength="4">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="card_type">Card Type</label>
        <input class="form-control" id="card_type" name="card_type" value="{{ old('card_type', $customer->card_type) }}" maxlength="255">
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
        <label class="form-label" for="address">Billing Address</label>
        <textarea class="form-control" id="address" name="address" rows="3">{{ old('address', $customer->address) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label" for="issue">Issue</label>
        <textarea class="form-control" id="issue" name="issue" rows="3">{{ old('issue', $customer->issue) }}</textarea>
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
