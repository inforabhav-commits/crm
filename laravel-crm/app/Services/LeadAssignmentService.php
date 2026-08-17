<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadAssignmentService
{
    public function assignToUser(Lead $lead, User $assignee, User $assignedBy, string $method = 'manual', ?Team $team = null): Lead
    {
        if (! $assignee->is_active) {
            throw ValidationException::withMessages(['owner_id' => 'Selected assignee must be active.']);
        }

        return DB::transaction(function () use ($lead, $assignee, $assignedBy, $method, $team) {
            $fromId = $lead->owner_id;

            $lead->forceFill([
                'owner_id' => $assignee->id,
                'updated_by_id' => $assignedBy->id,
            ])->save();

            $history = LeadAssignmentHistory::create([
                'lead_id' => $lead->id,
                'assigned_by_id' => $assignedBy->id,
                'assigned_from_id' => $fromId,
                'assigned_to_id' => $assignee->id,
                'team_id' => $team?->id,
                'method' => $method,
                'assigned_at' => now(),
            ]);

            app(AuditService::class)->log(
                $fromId ? 'lead.reassigned' : 'lead.assigned',
                $lead,
                $fromId ? 'Lead reassigned.' : 'Lead assigned.',
                ['owner_id' => $fromId],
                ['owner_id' => $assignee->id, 'method' => $method, 'team_id' => $team?->id],
                $assignedBy
            );

            app(CrmNotificationService::class)->notifyLeadAssignment($history);
            app(WorkflowRuleService::class)->dispatch($lead->refresh(), 'lead_assigned', ['event_key' => 'lead-assigned:'.$history->id], $assignedBy);

            return $lead->refresh();
        });
    }

    public function assignToTeam(Lead $lead, Team $team, User $assignedBy): Lead
    {
        $assignee = $this->eligibleAgents($team)->first();
        if (! $assignee) {
            throw ValidationException::withMessages(['team_id' => 'Selected team has no active eligible agents.']);
        }

        return $this->assignToUser($lead, $assignee, $assignedBy, 'team', $team);
    }

    public function assignRoundRobin(Lead $lead, Team $team, User $assignedBy): Lead
    {
        $eligibleAgents = $this->eligibleAgents($team);
        if ($eligibleAgents->isEmpty()) {
            throw ValidationException::withMessages(['team_id' => 'Selected team has no active eligible agents.']);
        }

        $lastAssignedToId = LeadAssignmentHistory::where('team_id', $team->id)
            ->where('method', 'round_robin')
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->value('assigned_to_id');

        $agentIds = $eligibleAgents->pluck('id')->values();
        $nextIndex = 0;

        if ($lastAssignedToId && ($index = $agentIds->search((int) $lastAssignedToId)) !== false) {
            $nextIndex = ($index + 1) % $agentIds->count();
        }

        $assignee = $eligibleAgents->firstWhere('id', $agentIds[$nextIndex]);

        return $this->assignToUser($lead, $assignee, $assignedBy, 'round_robin', $team);
    }

    public function eligibleAgents(Team $team)
    {
        if (! $team->is_active) {
            return collect();
        }

        return $team->members()
            ->where('users.is_active', true)
            ->whereHas('roles', function ($query) {
                $query->where('slug', 'agent');
            })
            ->orderBy('users.id')
            ->get();
    }
}
