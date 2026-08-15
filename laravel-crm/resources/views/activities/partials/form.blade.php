@csrf

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="subject">Subject</label>
        <input class="form-control" id="subject" name="subject" value="{{ old('subject', $activity->subject) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="activity_type_id">Activity Type</label>
        <select class="form-select" id="activity_type_id" name="activity_type_id" required>
            @foreach ($types as $type)
                <option value="{{ $type->id }}" @selected((string) old('activity_type_id', $activity->activity_type_id) === (string) $type->id)>{{ $type->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="lead_id">Related Lead</label>
        <select class="form-select" id="lead_id" name="lead_id">
            <option value="">None</option>
            @foreach ($leads as $lead)
                <option value="{{ $lead->id }}" @selected((string) old('lead_id', $activity->related_type === \App\Models\Lead::class ? $activity->related_id : null) === (string) $lead->id)>{{ $lead->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="customer_id">Related Customer</label>
        <select class="form-select" id="customer_id" name="customer_id">
            <option value="">None</option>
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $activity->related_type === \App\Models\Customer::class ? $activity->related_id : null) === (string) $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="contact_id">Related Contact</label>
        <select class="form-select" id="contact_id" name="contact_id">
            <option value="">None</option>
            @foreach ($contacts as $contact)
                <option value="{{ $contact->id }}" @selected((string) old('contact_id', $activity->related_type === \App\Models\Contact::class ? $activity->related_id : null) === (string) $contact->id)>{{ $contact->name }} - {{ $contact->customer?->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="opportunity_id">Related Opportunity</label>
        <select class="form-select" id="opportunity_id" name="opportunity_id">
            <option value="">None</option>
            @foreach ($opportunities as $opportunity)
                <option value="{{ $opportunity->id }}" @selected((string) old('opportunity_id', $activity->related_type === \App\Models\Opportunity::class ? $activity->related_id : null) === (string) $opportunity->id)>{{ $opportunity->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="assigned_user_id">Owner</label>
        <select class="form-select" id="assigned_user_id" name="assigned_user_id" required>
            @foreach ($owners as $owner)
                <option value="{{ $owner->id }}" @selected((string) old('assigned_user_id', $activity->assigned_user_id ?: auth()->id()) === (string) $owner->id)>{{ $owner->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="priority">Priority</label>
        <select class="form-select" id="priority" name="priority" required>
            @foreach ($priorities as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $activity->priority ?: 'normal') === $priority)>{{ ucfirst($priority) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="status">Status</label>
        <select class="form-select" id="status" name="status" required>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $activity->status ?: 'pending') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="due_at">Due Date / Time</label>
        <input class="form-control" id="due_at" type="datetime-local" name="due_at" value="{{ old('due_at', $activity->due_at?->format('Y-m-d\\TH:i')) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="reminder_at">Reminder Date / Time</label>
        <input class="form-control" id="reminder_at" type="datetime-local" name="reminder_at" value="{{ old('reminder_at', $activity->reminder_at?->format('Y-m-d\\TH:i')) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="next_action">Next Action</label>
        <input class="form-control" id="next_action" name="next_action" value="{{ old('next_action', $activity->next_action) }}" maxlength="5000">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Notes</label>
        <textarea class="form-control" id="description" name="description" rows="4">{{ old('description', $activity->description) }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="outcome">Outcome</label>
        <textarea class="form-control" id="outcome" name="outcome" rows="3">{{ old('outcome', $activity->outcome) }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="completion_notes">Completion Notes</label>
        <textarea class="form-control" id="completion_notes" name="completion_notes" rows="3">{{ old('completion_notes', $activity->completion_notes) }}</textarea>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('activities.index') }}">Cancel</a>
</div>
