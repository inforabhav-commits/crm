@csrf

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Opportunity Name</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $opportunity->name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="customer_id">Customer</label>
        <select class="form-select" id="customer_id" name="customer_id" required>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $opportunity->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="contact_id">Primary Contact</label>
        <select class="form-select" id="contact_id" name="contact_id">
            <option value="">None</option>
            @foreach ($contacts as $contact)
                <option value="{{ $contact->id }}" @selected((string) old('contact_id', $opportunity->contact_id) === (string) $contact->id)>{{ $contact->name }} - {{ $contact->customer?->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="owner_id">Owner / Agent</label>
        <select class="form-select" id="owner_id" name="owner_id" required>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((string) old('owner_id', $opportunity->owner_id ?: auth()->id()) === (string) $owner->id)>{{ $owner->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="stage_id">Stage</label>
        <select class="form-select" id="stage_id" name="stage_id" required>
            @foreach ($stages as $stage)
                <option value="{{ $stage->id }}" @selected((string) old('stage_id', $opportunity->stage_id) === (string) $stage->id)>{{ $stage->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="amount">Deal Value</label>
        <input class="form-control" id="amount" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $opportunity->amount) }}">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="currency">Currency</label>
        <input class="form-control text-uppercase" id="currency" name="currency" value="{{ old('currency', $opportunity->currency ?: 'USD') }}" required maxlength="3">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="probability">Probability</label>
        <input class="form-control" id="probability" type="number" min="0" max="100" name="probability" value="{{ old('probability', $opportunity->probability) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="expected_close_date">Expected Close</label>
        <input class="form-control" id="expected_close_date" type="date" name="expected_close_date" value="{{ old('expected_close_date', $opportunity->expected_close_date?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-8">
        <label class="form-label" for="next_step">Next Step</label>
        <input class="form-control" id="next_step" name="next_step" value="{{ old('next_step', $opportunity->next_step) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="loss_reason_id">Loss Reason</label>
        <select class="form-select" id="loss_reason_id" name="loss_reason_id">
            <option value="">None</option>
            @foreach ($lossReasons as $reason)
                <option value="{{ $reason->id }}" @selected((string) old('loss_reason_id', $opportunity->loss_reason_id) === (string) $reason->id)>{{ $reason->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="stage_notes">Stage Notes</label>
        <input class="form-control" id="stage_notes" name="stage_notes" value="{{ old('stage_notes') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $opportunity->description) }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="notes">Notes</label>
        <textarea class="form-control" id="notes" name="notes" rows="4">{{ old('notes', $opportunity->notes) }}</textarea>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('opportunities.index') }}">Cancel</a>
</div>
