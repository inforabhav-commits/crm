@csrf

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Name</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="email">Email</label>
        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="password">Password</label>
        <input class="form-control" id="password" type="password" name="password" autocomplete="new-password" @if (! $user->exists) required @endif>
        @if ($user->exists)
            <div class="form-text">Leave blank to keep the current password.</div>
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label" for="password_confirmation">Confirm Password</label>
        <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" @if (! $user->exists) required @endif>
    </div>
    <div class="col-12">
        <label class="form-label">Roles</label>
        <div class="row g-2">
            @foreach ($roles as $role)
                <div class="col-md-4">
                    <label class="border rounded d-block p-2">
                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $selectedRoles), true))>
                        {{ $role->display_name }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="reports_to_id">Reporting Manager</label>
        <select class="form-select" id="reports_to_id" name="reports_to_id">
            <option value="">None</option>
            @foreach ($reportingManagers as $manager)
                <option value="{{ $manager->id }}" @selected((string) old('reports_to_id', $user->reports_to_id) === (string) $manager->id)>
                    {{ $manager->name }} ({{ $manager->email }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Teams</label>
        <div class="row g-2">
            @forelse ($teams as $team)
                <div class="col-md-4">
                    <label class="border rounded d-block p-2">
                        <input type="checkbox" name="teams[]" value="{{ $team->id }}" @checked(in_array($team->id, old('teams', $selectedTeams), true))>
                        {{ $team->name }}
                    </label>
                </div>
            @empty
                <div class="col-12 text-muted">No active teams yet.</div>
            @endforelse
        </div>
    </div>
    <div class="col-12">
        <label class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
            <span class="form-check-label">Active user</span>
        </label>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Cancel</a>
</div>
