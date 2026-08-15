@csrf

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Team Name</label>
        <input class="form-control" id="name" name="name" value="{{ old('name', $team->name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="manager_id">Team Manager</label>
        <select class="form-select" id="manager_id" name="manager_id">
            <option value="">None</option>
            @foreach ($activeUsers as $user)
                <option value="{{ $user->id }}" @selected((string) old('manager_id', $team->manager_id) === (string) $user->id)>
                    {{ $user->name }} ({{ $user->email }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $team->description) }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Members</label>
        <div class="row g-2">
            @foreach ($activeUsers as $user)
                <div class="col-md-4">
                    <label class="border rounded d-block p-2">
                        <input type="checkbox" name="members[]" value="{{ $user->id }}" @checked(in_array($user->id, old('members', $selectedMembers), true))>
                        {{ $user->name }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
    <div class="col-12">
        <label class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $team->is_active))>
            <span class="form-check-label">Active team</span>
        </label>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.teams.index') }}">Cancel</a>
</div>
