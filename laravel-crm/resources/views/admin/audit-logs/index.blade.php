@extends('layouts.crm', ['title' => 'Audit Log'])

@section('content')
    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2" method="get" action="{{ route('admin.audit-logs.index') }}">
                <div class="col-md-3">
                    <label class="form-label" for="user">User</label>
                    <select class="form-select" id="user" name="user">
                        <option value="">All users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) ($filters['user'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="action">Action</label>
                    <input class="form-control" id="action" name="action" value="{{ $filters['action'] ?? '' }}" list="audit-actions">
                    <datalist id="audit-actions">
                        @foreach ($actions as $action)
                            <option value="{{ $action }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="entity_type">Entity</label>
                    <select class="form-select" id="entity_type" name="entity_type">
                        <option value="">All entities</option>
                        @foreach ($entityTypes as $entityType)
                            <option value="{{ $entityType }}" @selected(($filters['entity_type'] ?? '') === $entityType)>{{ class_basename($entityType) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="date">Date</label>
                    <input class="form-control" id="date" type="date" name="date" value="{{ $filters['date'] ?? '' }}">
                </div>
                <div class="col-md-1 d-grid align-items-end">
                    <button class="btn btn-outline-primary" type="submit">Filter</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $log->user?->name ?? 'System' }}</td>
                            <td><span class="badge text-bg-secondary rounded-1">{{ $log->action }}</span></td>
                            <td>{{ $log->entity_type ? class_basename($log->entity_type).' #'.$log->entity_id : '-' }}</td>
                            <td>
                                <div>{{ $log->description }}</div>
                                @if ($log->old_values || $log->new_values)
                                    <details class="small text-muted mt-1">
                                        <summary>Changes</summary>
                                        <pre class="mb-1">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT) }}</pre>
                                    </details>
                                @endif
                            </td>
                            <td>{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="6">No audit records found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        </div>
    </section>
@endsection
