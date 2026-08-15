<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        $this->authorize('teams.view');

        return view('admin.teams.index', [
            'teams' => Team::with(['manager', 'members'])->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        $this->authorize('teams.create');

        return view('admin.teams.create', $this->formData(new Team(['is_active' => true]), []));
    }

    public function store(Request $request, AuditService $audit)
    {
        $this->authorize('teams.create');

        $validated = $this->validatedTeam($request);

        $team = Team::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $team->members()->sync($validated['members'] ?? []);
        $audit->created($team, 'team.created', 'Team created.', $request->user(), $request);
        $audit->relationChanged($team, 'team.members_changed', 'member_ids', [], $validated['members'] ?? [], 'Team members assigned.', $request->user(), $request);

        return redirect()->route('admin.teams.index')->with('status', 'Team created.');
    }

    public function edit(Team $team)
    {
        $this->authorize('teams.edit');

        return view('admin.teams.edit', $this->formData($team->load('members'), $team->members->pluck('id')->all()));
    }

    public function update(Request $request, Team $team, AuditService $audit)
    {
        $this->authorize('teams.edit');

        $validated = $this->validatedTeam($request, $team);
        $before = $team->getAttributes();
        $beforeMemberIds = $team->members()->pluck('users.id')->all();

        $team->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'manager_id' => $validated['manager_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ])->save();

        if ($request->user()->can('teams.manage_members')) {
            $team->members()->sync($validated['members'] ?? []);
        }
        $audit->updated($team, $before, 'team.updated', 'Team updated.', $request->user(), $request);
        if ($request->user()->can('teams.manage_members')) {
            $audit->relationChanged($team, 'team.members_changed', 'member_ids', $beforeMemberIds, $validated['members'] ?? [], 'Team members changed.', $request->user(), $request);
        }

        return redirect()->route('admin.teams.index')->with('status', 'Team updated.');
    }

    public function toggleStatus(Request $request, Team $team, AuditService $audit)
    {
        $this->authorize('teams.edit');

        $before = $team->getAttributes();
        $team->forceFill(['is_active' => ! $team->is_active])->save();
        $audit->updated($team, $before, $team->is_active ? 'team.activated' : 'team.deactivated', $team->is_active ? 'Team activated.' : 'Team deactivated.', $request->user(), $request);

        return back()->with('status', $team->is_active ? 'Team activated.' : 'Team deactivated.');
    }

    private function formData(Team $team, array $selectedMembers): array
    {
        $activeUsers = User::where('is_active', true)->orderBy('name')->get();

        return [
            'team' => $team,
            'activeUsers' => $activeUsers,
            'selectedMembers' => $selectedMembers,
        ];
    }

    private function validatedTeam(Request $request, ?Team $team = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($team)],
            'description' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'is_active' => ['nullable', 'boolean'],
            'members' => ['nullable', 'array'],
            'members.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
        ]);
    }
}
