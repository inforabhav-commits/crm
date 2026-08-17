@extends('layouts.crm', ['title' => 'Workflow Rules'])

@section('content')
    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-0">Workflow Rules</h2><div class="text-muted small">Simple event-driven CRM actions with execution history.</div></div><a class="btn btn-primary" href="{{ route('admin.workflows.create') }}">Create Rule</a></div>
    <section class="card border-0 shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Priority</th><th>Name</th><th>Entity / Trigger</th><th>Action</th><th>Status</th><th>Last execution</th><th class="text-end">Actions</th></tr></thead><tbody>
    @forelse ($rules as $rule)
        @php($last = $rule->executions->sortByDesc('created_at')->first())
        <tr><td>{{ $rule->priority }}</td><td>{{ $rule->name }}</td><td>{{ class_basename($rule->entity_type) }} / {{ $rule->trigger }}</td><td>{{ $rule->action['type'] ?? '-' }}</td><td>{{ $rule->is_active ? 'Active' : 'Inactive' }}</td><td>{{ $last ? $last->status.' '.optional($last->executed_at)->format('Y-m-d H:i') : 'Never' }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.workflows.edit', $rule) }}">Edit</a><form class="d-inline" method="post" action="{{ route('admin.workflows.toggle', $rule) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary" type="submit">{{ $rule->is_active ? 'Deactivate' : 'Activate' }}</button></form></td></tr>
    @empty<tr><td colspan="7" class="text-muted">No workflow rules configured.</td></tr>@endforelse
    </tbody></table></div>{{ $rules->links() }}</div></section>
@endsection
