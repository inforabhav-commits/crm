@extends('layouts.crm', ['title' => 'Roles & Permissions'])

@section('content')
    <section class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-1">Roles & Permissions</h2>
            <p class="text-muted">Permission UI will expand in a later module. Current assignments are seeded for L02.</p>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Role</th>
                        <th>Permissions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>{{ $role->name }}</td>
                            <td>{{ $role->permissions->pluck('slug')->join(', ') ?: 'No explicit permissions' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
