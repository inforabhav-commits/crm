<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $this->authorize('users.view');

        return view('admin.users.index', [
            'users' => User::with(['roles', 'teams', 'manager'])->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        $this->authorize('users.create');

        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
            'user' => new User(['is_active' => true]),
            'selectedRoles' => [],
            'teams' => Team::where('is_active', true)->orderBy('name')->get(),
            'selectedTeams' => [],
            'reportingManagers' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('users.create');

        $validated = $this->validatedUser($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => $request->boolean('is_active'),
            'reports_to_id' => $validated['reports_to_id'] ?? null,
        ]);

        $user->roles()->sync($validated['roles'] ?? []);
        $user->teams()->sync($validated['teams'] ?? []);
        $audit->created($user->load('roles', 'teams'), 'user.created', 'User created.', $request->user(), $request);
        $audit->relationChanged($user, 'user.roles_changed', 'role_ids', [], $validated['roles'] ?? [], 'User roles assigned.', $request->user(), $request);
        $audit->relationChanged($user, 'user.teams_changed', 'team_ids', [], $validated['teams'] ?? [], 'User teams assigned.', $request->user(), $request);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user)
    {
        $this->authorize('users.edit');

        return view('admin.users.edit', [
            'roles' => Role::orderBy('name')->get(),
            'user' => $user->load(['roles', 'teams']),
            'selectedRoles' => $user->roles->pluck('id')->all(),
            'teams' => Team::where('is_active', true)->orWhereHas('members', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })->orderBy('name')->get(),
            'selectedTeams' => $user->teams->pluck('id')->all(),
            'reportingManagers' => User::where('is_active', true)->where('id', '!=', $user->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        $this->authorize('users.edit');

        $validated = $this->validatedUser($request, $user);
        $requestedRoleIds = $validated['roles'] ?? [];

        if ($request->user()->is($user) && ! $user->hasRole('super-admin') && $this->roleIdsChanged($user, $requestedRoleIds)) {
            return back()
                ->withErrors(['roles' => 'You cannot change your own roles.'])
                ->withInput();
        }

        if ($request->user()->is($user) && $user->hasRole('super-admin') && ! $this->roleIdsIncludeSuperAdmin($requestedRoleIds)) {
            return back()
                ->withErrors(['roles' => 'You cannot remove your own Super Admin role.'])
                ->withInput();
        }

        if ($this->wouldRemoveOnlyActiveSuperAdmin($user, $request->boolean('is_active'), $requestedRoleIds)) {
            return back()
                ->withErrors(['roles' => 'At least one active Super Admin must remain.'])
                ->withInput();
        }

        if ($user->wouldCreateReportingCycle(isset($validated['reports_to_id']) ? (int) $validated['reports_to_id'] : null)) {
            return back()
                ->withErrors(['reports_to_id' => 'Reporting manager creates an invalid hierarchy.'])
                ->withInput();
        }

        $before = $user->getAttributes();
        $beforeRoleIds = $user->roles()->pluck('roles.id')->all();
        $beforeTeamIds = $user->teams()->pluck('teams.id')->all();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $request->boolean('is_active'),
            'reports_to_id' => $validated['reports_to_id'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->roles()->sync($requestedRoleIds);
        $user->teams()->sync($validated['teams'] ?? []);
        $audit->updated($user, $before, 'user.updated', 'User updated.', $request->user(), $request);
        $audit->relationChanged($user, 'user.roles_changed', 'role_ids', $beforeRoleIds, $requestedRoleIds, 'User roles changed.', $request->user(), $request);
        $audit->relationChanged($user, 'user.teams_changed', 'team_ids', $beforeTeamIds, $validated['teams'] ?? [], 'User teams changed.', $request->user(), $request);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function toggleStatus(Request $request, User $user, AuditService $audit)
    {
        $this->authorize('users.activate');

        $newStatus = ! $user->is_active;

        if ($this->wouldRemoveOnlyActiveSuperAdmin($user, $newStatus, $user->roles()->pluck('roles.id')->all())) {
            return back()->withErrors(['user' => 'At least one active Super Admin must remain.']);
        }

        $before = $user->getAttributes();
        $user->forceFill(['is_active' => $newStatus])->save();
        $audit->updated($user, $before, $newStatus ? 'user.activated' : 'user.deactivated', $newStatus ? 'User activated.' : 'User deactivated.', $request->user(), $request);

        return back()->with('status', $newStatus ? 'User activated.' : 'User deactivated.');
    }

    private function validatedUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'teams' => ['nullable', 'array'],
            'teams.*' => ['integer', 'exists:teams,id'],
            'reports_to_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
        ]);
    }

    private function roleIdsIncludeSuperAdmin(array $roleIds): bool
    {
        return Role::whereIn('id', $roleIds)->where('slug', 'super-admin')->exists();
    }

    private function roleIdsChanged(User $user, array $roleIds): bool
    {
        $currentRoleIds = $user->roles()->pluck('roles.id')->sort()->values()->all();
        $requestedRoleIds = collect($roleIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $currentRoleIds !== $requestedRoleIds;
    }

    private function wouldRemoveOnlyActiveSuperAdmin(User $user, bool $newStatus, array $roleIds): bool
    {
        $userIsOrWillBeSuperAdmin = $this->roleIdsIncludeSuperAdmin($roleIds);
        if ($newStatus && $userIsOrWillBeSuperAdmin) {
            return false;
        }

        if (! $user->hasRole('super-admin')) {
            return false;
        }

        return User::where('is_active', true)
            ->where('id', '!=', $user->id)
            ->whereHas('roles', function ($query) {
                $query->where('slug', 'super-admin');
            })
            ->doesntExist();
    }
}
