@extends('layouts.crm', ['title' => 'Roles & Permissions'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-1">Roles & Permissions</h2>
            <p class="text-muted mb-3">Current seeded roles with module-level permission coverage. Internal role slugs remain unchanged.</p>

            @foreach ($roles as $role)
                @php
                    $granted = $role->permissions->keyBy('slug');
                    $sensitive = $role->permissions
                        ->whereIn('slug', ['export.crm', 'calls.recordings.view', 'reports.view', 'roles.manage', 'crm_settings.manage', 'justcall.manage', 'justcall.monitor', 'ops.view', 'audit.view'])
                        ->pluck('slug');
                @endphp

                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                        <div>
                            <h3 class="h6 mb-1">{{ $role->display_name }}</h3>
                            <div class="text-muted small">Slug: {{ $role->slug }}</div>
                        </div>
                        <div class="text-end">
                            <span class="badge text-bg-light border">{{ $role->permissions->count() }} permissions</span>
                            @if ($sensitive->isNotEmpty())
                                <div class="small text-muted mt-1">Sensitive: {{ $sensitive->join(', ') }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th style="width: 180px">Module</th>
                                <th>Permissions</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($permissionModules as $module => $permissions)
                                @php
                                    $moduleGranted = $permissions->filter(fn ($permission) => $granted->has($permission->slug));
                                @endphp
                                @if ($moduleGranted->isNotEmpty())
                                    <tr>
                                        <td class="fw-semibold">{{ $module }}</td>
                                        <td>
                                            @foreach ($moduleGranted as $permission)
                                                <span class="badge text-bg-primary me-1 mb-1" title="{{ $permission->name }}">{{ $permission->slug }}</span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
