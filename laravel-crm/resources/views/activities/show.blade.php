@extends('layouts.crm', ['title' => 'Activity Detail'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $activity->subject }}</h2>
                    <p class="text-muted mb-0">{{ $activity->type?->name ?? 'Activity' }}</p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('activities.index') }}">Back</a>
                    @can('activities.edit')
                        <a class="btn btn-primary" href="{{ route('activities.edit', $activity) }}">Edit</a>
                    @endcan
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">Owner</div><div>{{ $activity->assignedUser?->name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Status</div><div>{{ ucfirst($activity->status) }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Priority</div><div>{{ ucfirst($activity->priority) }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Due</div><div>{{ $activity->due_at?->format('Y-m-d H:i') }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Reminder</div><div>{{ $activity->reminder_at?->format('Y-m-d H:i') ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Completed</div><div>{{ $activity->completed_at?->format('Y-m-d H:i') ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Created By</div><div>{{ $activity->createdBy?->name ?? '-' }}</div></div>
                <div class="col-md-3">
                    <div class="text-muted small">Related</div>
                    <div>
                        @if ($activity->related instanceof \App\Models\Lead)
                            <a href="{{ route('leads.show', $activity->related) }}">{{ $activity->related->name }}</a>
                        @elseif ($activity->related instanceof \App\Models\Customer)
                            <a href="{{ route('customers.show', $activity->related) }}">{{ $activity->related->name }}</a>
                        @elseif ($activity->related instanceof \App\Models\Contact)
                            <a href="{{ route('contacts.show', $activity->related) }}">{{ $activity->related->name }}</a>
                        @elseif ($activity->related instanceof \App\Models\Opportunity)
                            <a href="{{ route('opportunities.show', $activity->related) }}">{{ $activity->related->name }}</a>
                        @else
                            -
                        @endif
                    </div>
                </div>
                <div class="col-12"><div class="text-muted small">Notes</div><div class="border rounded p-3 bg-light">{{ $activity->description ?: 'No notes recorded.' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Outcome</div><div>{{ $activity->outcome ?: '-' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Completion Notes</div><div>{{ $activity->completion_notes ?: '-' }}</div></div>
                <div class="col-md-4"><div class="text-muted small">Next Action</div><div>{{ $activity->next_action ?: '-' }}</div></div>
            </div>
        </div>
    </section>

    @can('activities.complete')
        @if ($activity->status !== 'completed')
            <section class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Complete Activity</h2>
                    <form class="row g-3" method="post" action="{{ route('activities.complete', $activity) }}">
                        @csrf
                        @method('patch')
                        @if ($errors->any())
                            <div class="col-12">
                                <div class="alert alert-danger">{{ $errors->first() }}</div>
                            </div>
                        @endif
                        <div class="col-md-6">
                            <label class="form-label" for="outcome">Outcome</label>
                            <textarea class="form-control" id="outcome" name="outcome" rows="3" required>{{ old('outcome') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="completion_notes">Notes</label>
                            <textarea class="form-control" id="completion_notes" name="completion_notes" rows="3">{{ old('completion_notes') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="next_action">Next Action</label>
                            <input class="form-control" id="next_action" name="next_action" value="{{ old('next_action') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="next_follow_up_at">Next Follow-up</label>
                            <input class="form-control" id="next_follow_up_at" type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="next_follow_up_type_id">Next Type</label>
                            <select class="form-select" id="next_follow_up_type_id" name="next_follow_up_type_id">
                                <option value="">Use same type</option>
                                @foreach ($nextFollowUpTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="next_follow_up_subject">Next Subject</label>
                            <input class="form-control" id="next_follow_up_subject" name="next_follow_up_subject" value="{{ old('next_follow_up_subject') }}" maxlength="255">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-success" type="submit">Complete Activity</button>
                        </div>
                    </form>
                </div>
            </section>
        @endif
    @endcan
@endsection
