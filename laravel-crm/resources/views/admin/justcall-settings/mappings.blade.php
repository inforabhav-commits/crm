@extends('layouts.crm', ['title' => 'JustCall User Mapping'])

@section('content')
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 mb-0">JustCall User Mapping</h2>
            <div class="text-muted small">CRM users stay authoritative for roles and permissions.</div>
        </div>
        @can('justcall.manage')
            <form method="post" action="{{ route('admin.justcall-mappings.fetch') }}">
                @csrf
                <button class="btn btn-outline-primary btn-sm" type="submit">Fetch JustCall Users</button>
            </form>
        @endcan
    </div>

    @if ($justCallResult)
        <div class="alert {{ $justCallResult['ok'] ? 'alert-success' : 'alert-warning' }}">
            {{ $justCallResult['message'] }} @if($justCallResult['ok']) Found {{ count($justCallUsers) }} users. @endif
        </div>
    @endif

    <section class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h3 class="h6">Create Mapping</h3>
            <form class="row g-2" method="post" action="{{ route('admin.justcall-mappings.store') }}">
                @csrf
                <div class="col-md-3">
                    <label class="form-label" for="user_id">CRM User</label>
                    <select class="form-select" id="user_id" name="user_id" required>
                        <option value="">Choose active user</option>
                        @foreach ($users->where('is_active', true) as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="justcall_user_id">JustCall ID</label>
                    <input class="form-control" id="justcall_user_id" name="justcall_user_id" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="justcall_name">Name</label>
                    <input class="form-control" id="justcall_name" name="justcall_name">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="justcall_email">Email</label>
                    <input class="form-control" id="justcall_email" name="justcall_email" type="email">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="justcall_phone">Phone/Extension</label>
                    <input class="form-control" id="justcall_phone" name="justcall_phone">
                </div>
                <div class="col-md-1 d-grid align-items-end">
                    <button class="btn btn-primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </section>

    @if ($suggestions)
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h3 class="h6">Suggested Matches</h3>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>CRM User</th><th>Suggested JustCall User</th><th>Reason</th><th class="text-end">Confirm</th></tr></thead>
                        <tbody>
                        @foreach ($suggestions as $userId => $match)
                            @php
                                $user = $users->firstWhere('id', $userId);
                            @endphp
                            <tr>
                                <td>{{ $user?->name }}<br><small class="text-muted">{{ $user?->email }}</small></td>
                                <td>{{ $match['name'] ?? '-' }}<br><small class="text-muted">{{ $match['email'] ?? '-' }} / {{ $match['id'] }}</small></td>
                                <td>{{ ucfirst($match['match_reason']) }}</td>
                                <td class="text-end">
                                    <form method="post" action="{{ route('admin.justcall-mappings.store') }}">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $userId }}">
                                        <input type="hidden" name="justcall_user_id" value="{{ $match['id'] }}">
                                        <input type="hidden" name="justcall_name" value="{{ $match['name'] }}">
                                        <input type="hidden" name="justcall_email" value="{{ $match['email'] }}">
                                        <input type="hidden" name="justcall_phone" value="{{ $match['phone'] }}">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Confirm</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h3 class="h6">Current Mappings</h3>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>CRM User</th><th>JustCall User</th><th>Status</th><th>Verified</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($mappings as $mapping)
                        <tr>
                            <td>{{ $mapping->user?->name }}<br><small class="text-muted">{{ $mapping->user?->email }}</small></td>
                            <td>{{ $mapping->justcall_name ?: '-' }}<br><small class="text-muted">{{ $mapping->justcall_email ?: '-' }} / {{ $mapping->justcall_user_id }}</small></td>
                            <td>{{ $mapping->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>{{ $mapping->last_verified_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td class="text-end">
                                @can('justcall.manage')
                                    <div class="d-inline-flex gap-2">
                                        <form method="post" action="{{ route('admin.justcall-mappings.verify', $mapping) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Verify</button>
                                        </form>
                                        @if ($mapping->is_active)
                                            <form method="post" action="{{ route('admin.justcall-mappings.disable', $mapping) }}">
                                                @csrf
                                                @method('patch')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Disable</button>
                                            </form>
                                        @endif
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-muted" colspan="5">No JustCall mappings found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $mappings->links() }}
        </div>
    </section>
@endsection
