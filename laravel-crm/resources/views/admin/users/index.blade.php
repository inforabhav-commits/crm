@extends('layouts.crm', ['title' => 'Users'])

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2 class="h5 mb-1">User Management</h2>
                    <p class="text-muted mb-0">Create users, assign roles, and control account access.</p>
                </div>
                @can('users.create')
                    <a class="btn btn-primary" href="{{ route('admin.users.create') }}">Create User</a>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Teams</th>
                        <th>Reports To</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->roles->pluck('name')->join(', ') ?: 'None' }}</td>
                            <td>{{ $user->teams->pluck('name')->join(', ') ?: 'None' }}</td>
                            <td>{{ $user->manager?->name ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('users.edit')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $user) }}">Edit</a>
                                @endcan
                                @can('users.activate')
                                    <form class="d-inline" method="post" action="{{ route('admin.users.status', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                            {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted" colspan="7">No users found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $users->links() }}
        </div>
    </section>
@endsection
