<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadAssignmentController extends Controller
{
    public function store(Request $request, Lead $lead, LeadAssignmentService $assignmentService)
    {
        $this->authorize('leads.assign');
        abort_unless(Lead::whereKey($lead->id)->visibleTo($request->user())->exists(), 403);

        $validated = $request->validate([
            'assignment_method' => ['required', Rule::in(['manual', 'team', 'round_robin'])],
            'owner_id' => ['required_if:assignment_method,manual', 'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'team_id' => ['required_if:assignment_method,team,round_robin', 'nullable', 'integer', Rule::exists('teams', 'id')->where('is_active', true)],
        ]);

        if ($validated['assignment_method'] === 'manual') {
            $assignmentService->assignToUser($lead, User::findOrFail($validated['owner_id']), $request->user());
        } elseif ($validated['assignment_method'] === 'team') {
            $assignmentService->assignToTeam($lead, Team::findOrFail($validated['team_id']), $request->user());
        } else {
            $assignmentService->assignRoundRobin($lead, Team::findOrFail($validated['team_id']), $request->user());
        }

        return redirect()->route('leads.show', $lead)->with('status', 'Lead assigned.');
    }
}
