@extends('layouts.crm', ['title' => $rule->exists ? 'Edit Workflow Rule' : 'Create Workflow Rule'])

@section('content')
<section class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5">{{ $rule->exists ? 'Edit' : 'Create' }} Workflow Rule</h2><p class="text-muted small">Conditions and action options are structured JSON. Example condition: {&quot;lead_status&quot;:&quot;qualified&quot;}.</p>
<form method="post" action="{{ $rule->exists ? route('admin.workflows.update', $rule) : route('admin.workflows.store') }}" class="row g-3">@csrf @if($rule->exists) @method('PUT') @endif
<div class="col-md-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="{{ old('name', $rule->name) }}" required></div>
<div class="col-md-3"><label class="form-label" for="entity_type">Entity</label><select class="form-select" id="entity_type" name="entity_type">@foreach($entities as $value => $label)<option value="{{ $value }}" @selected(old('entity_type', $rule->entity_type) === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label" for="trigger">Trigger</label><select class="form-select" id="trigger" name="trigger">@foreach($triggers as $trigger)<option value="{{ $trigger }}" @selected(old('trigger', $rule->trigger) === $trigger)>{{ $trigger }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label" for="conditions_json">Conditions JSON</label><textarea class="form-control" id="conditions_json" name="conditions_json" rows="5">{{ old('conditions_json', $rule->exists ? json_encode($rule->conditions, JSON_PRETTY_PRINT) : '{}') }}</textarea></div>
<div class="col-md-6"><label class="form-label" for="action_json">Action JSON</label><textarea class="form-control" id="action_json" name="action_json" rows="5">{{ old('action_json', $rule->exists ? json_encode(collect($rule->action)->except(['type'])->all(), JSON_PRETTY_PRINT) : '{}') }}</textarea></div>
<div class="col-md-3"><label class="form-label" for="action_type">Action</label><select class="form-select" id="action_type" name="action_type">@foreach($actions as $value => $label)<option value="{{ $value }}" @selected(old('action_type', $rule->action['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-2"><label class="form-label" for="priority">Priority</label><input class="form-control" id="priority" name="priority" type="number" min="0" max="9999" value="{{ old('priority', $rule->priority ?? 0) }}"></div>
<div class="col-md-2 form-check mt-5 ms-3"><input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $rule->exists ? $rule->is_active : true))><label class="form-check-label" for="is_active">Active</label></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Save Rule</button> <a class="btn btn-outline-secondary" href="{{ route('admin.workflows.index') }}">Cancel</a></div>
</form></div></section>
@endsection
