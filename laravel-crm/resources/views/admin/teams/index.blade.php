@extends('layouts.crm', ['title' => 'Teams'])

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
                    <h2 class="h5 mb-1">Teams</h2>
                    <p class="text-muted mb-0">Organize managers, team leaders, and agents for future assignment and reporting.</p>
                </div>
                @can('teams.create')
                    <a class="btn btn-primary" href="{{ route('admin.teams.create') }}">Create Team</a>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Manager</th>
                        <th>Members</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($teams as $team)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $team->name }}</div>
                                @if ($team->description)
                                    <small class="text-muted">{{ $team->description }}</small>
                                @endif
                            </td>
                            <td>{{ $team->manager?->name ?? '-' }}</td>
                            <td>{{ $team->members->count() }}</td>
                            <td>
                                <span class="badge {{ $team->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                    {{ $team->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-end">
                                @can('teams.edit')
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.teams.edit', $team) }}">Edit</a>
                                    <form class="d-inline" method="post" action="{{ route('admin.teams.status', $team) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                            {{ $team->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-muted" colspan="5">No teams found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{ $teams->links() }}
        </div>
    </section>
@endsection
