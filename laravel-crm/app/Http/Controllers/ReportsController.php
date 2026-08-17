<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\CrmMasterValue;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportsController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('reports.view');

        $user = $request->user();
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'owner' => ['nullable', 'integer'],
            'team' => ['nullable', 'integer'],
            'lead_status' => ['nullable', 'integer'],
            'stage' => ['nullable', 'integer'],
            'source' => ['nullable', 'integer'],
            'activity_status' => ['nullable', 'in:pending,completed,cancelled'],
            'direction' => ['nullable', 'in:inbound,outbound,unknown'],
            'call_status' => ['nullable', 'string', 'max:50'],
        ]);

        $ownerIds = $this->filteredOwnerIds($user, $filters);
        $leads = $this->leadQuery($user, $ownerIds, $filters);
        $opportunities = $this->opportunityQuery($user, $ownerIds, $filters);
        $activities = $this->activityQuery($user, $ownerIds, $filters);
        $calls = $this->callQuery($user, $ownerIds, $filters);

        return view('reports.index', [
            'filters' => $filters,
            'owners' => $this->ownerOptions($user),
            'teams' => $this->teamOptions($user),
            'leadStatuses' => $this->masterValues('lead_status'),
            'leadSources' => $this->masterValues('lead_source'),
            'stages' => $this->masterValues('opportunity_stage'),
            'callStatuses' => (clone $calls)->whereNotNull('status')->distinct()->orderBy('status')->pluck('status'),
            'leadFunnel' => $this->leadFunnel($leads),
            'sourcePerformance' => $this->sourcePerformance($leads),
            'leadAging' => $this->leadAging($leads),
            'pipeline' => $this->pipeline($opportunities),
            'wonLost' => $this->wonLost($opportunities),
            'agentPerformance' => $this->agentPerformance($ownerIds, $leads, $opportunities, $activities, $calls),
            'activityReport' => $this->activityReport($activities),
            'callReport' => $this->callReport($calls),
        ]);
    }

    private function leadFunnel(Builder $query): Collection
    {
        return (clone $query)->selectRaw('lead_status_id, COUNT(*) as total')->groupBy('lead_status_id')->get()->keyBy('lead_status_id');
    }

    private function sourcePerformance(Builder $query): Collection
    {
        return (clone $query)->selectRaw('lead_source_id, COUNT(*) as total')->groupBy('lead_source_id')->get()->keyBy('lead_source_id');
    }

    private function leadAging(Builder $query): array
    {
        $now = now();
        $rows = (clone $query)->select(['created_at'])->get();
        return [
            '0-7 days' => $rows->filter(fn (Lead $lead) => $lead->created_at && $lead->created_at->diffInDays($now) <= 7)->count(),
            '8-30 days' => $rows->filter(fn (Lead $lead) => $lead->created_at && $lead->created_at->diffInDays($now) > 7 && $lead->created_at->diffInDays($now) <= 30)->count(),
            '31-90 days' => $rows->filter(fn (Lead $lead) => $lead->created_at && $lead->created_at->diffInDays($now) > 30 && $lead->created_at->diffInDays($now) <= 90)->count(),
            '91+ days' => $rows->filter(fn (Lead $lead) => $lead->created_at && $lead->created_at->diffInDays($now) > 90)->count(),
        ];
    }

    private function pipeline(Builder $query): Collection
    {
        return (clone $query)->selectRaw('stage_id, COUNT(*) as total, COALESCE(SUM(amount), 0) as amount')->where('status', 'open')->groupBy('stage_id')->get()->keyBy('stage_id');
    }

    private function wonLost(Builder $query): Collection
    {
        return (clone $query)->selectRaw("status, COUNT(*) as total, COALESCE(SUM(amount), 0) as amount")->whereIn('status', ['won', 'lost'])->groupBy('status')->get()->keyBy('status');
    }

    private function agentPerformance(?array $ownerIds, Builder $leads, Builder $opportunities, Builder $activities, Builder $calls): Collection
    {
        $ids = $ownerIds ?? User::where('is_active', true)->pluck('id')->all();
        $users = User::whereIn('id', $ids)->orderBy('name')->get()->keyBy('id');
        $leadCounts = (clone $leads)->selectRaw('owner_id, COUNT(*) as total')->groupBy('owner_id')->pluck('total', 'owner_id');
        $opportunityCounts = (clone $opportunities)->selectRaw('owner_id, COUNT(*) as total')->groupBy('owner_id')->pluck('total', 'owner_id');
        $activityCounts = (clone $activities)->selectRaw('assigned_user_id, COUNT(*) as total')->groupBy('assigned_user_id')->pluck('total', 'assigned_user_id');
        $callCounts = (clone $calls)->selectRaw('user_id, COUNT(*) as total')->groupBy('user_id')->pluck('total', 'user_id');

        return $users->map(fn (User $user) => [
            'user' => $user,
            'leads' => (int) ($leadCounts[$user->id] ?? 0),
            'opportunities' => (int) ($opportunityCounts[$user->id] ?? 0),
            'activities' => (int) ($activityCounts[$user->id] ?? 0),
            'calls' => (int) ($callCounts[$user->id] ?? 0),
        ])->values();
    }

    private function activityReport(Builder $query): Collection
    {
        return (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->orderBy('status')->get()->keyBy('status');
    }

    private function callReport(Builder $query): Collection
    {
        return (clone $query)->selectRaw('direction, status, COUNT(*) as total')->groupBy('direction', 'status')->orderBy('direction')->orderBy('status')->get();
    }

    private function leadQuery(User $user, ?array $ownerIds, array $filters): Builder
    {
        $query = Lead::visibleTo($user);
        $this->applyOwnerFilter($query, $ownerIds);
        $this->applyDateFilter($query, 'created_at', $filters);
        if (! empty($filters['lead_status'])) $query->where('lead_status_id', $filters['lead_status']);
        if (! empty($filters['source'])) $query->where('lead_source_id', $filters['source']);
        return $query;
    }

    private function opportunityQuery(User $user, ?array $ownerIds, array $filters): Builder
    {
        $query = Opportunity::visibleTo($user);
        $this->applyOwnerFilter($query, $ownerIds);
        $this->applyDateFilter($query, 'created_at', $filters);
        if (! empty($filters['stage'])) $query->where('stage_id', $filters['stage']);
        return $query;
    }

    private function activityQuery(User $user, ?array $ownerIds, array $filters): Builder
    {
        $query = Activity::visibleTo($user);
        if ($ownerIds !== null) $query->whereIn('assigned_user_id', $ownerIds);
        $this->applyDateFilter($query, 'due_at', $filters);
        if (! empty($filters['activity_status'])) $query->where('status', $filters['activity_status']);
        return $query;
    }

    private function callQuery(User $user, ?array $ownerIds, array $filters): Builder
    {
        $query = CallLog::visibleTo($user);
        if ($ownerIds !== null) $query->whereIn('user_id', $ownerIds);
        $this->applyDateFilter($query, 'last_event_at', $filters);
        if (! empty($filters['direction'])) $query->where('direction', $filters['direction']);
        if (! empty($filters['call_status'])) $query->where('status', $filters['call_status']);
        return $query;
    }

    private function applyDateFilter(Builder $query, string $column, array $filters): void
    {
        if (! empty($filters['from'])) $query->where($column, '>=', Carbon::parse($filters['from'])->startOfDay());
        if (! empty($filters['to'])) $query->where($column, '<=', Carbon::parse($filters['to'])->endOfDay());
    }

    private function filteredOwnerIds(User $user, array $filters): ?array
    {
        $allowedIds = $this->ownerOptions($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! empty($filters['team'])) {
            $teamIds = Team::whereKey($filters['team'])->where('is_active', true)->first()?->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all() ?? [];
            $allowedIds = array_values(array_intersect($allowedIds, $teamIds));
        }
        if (! empty($filters['owner'])) return in_array((int) $filters['owner'], $allowedIds, true) ? [(int) $filters['owner']] : [];
        return $allowedIds;
    }

    private function applyOwnerFilter(Builder $query, ?array $ownerIds): void
    {
        if ($ownerIds !== null) $query->whereIn('owner_id', $ownerIds);
    }

    private function ownerOptions(User $user): Collection
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) return User::where('is_active', true)->orderBy('name')->get();
        $ids = $user->reportingTreeUserIds();
        $ids[] = $user->id;
        return User::whereIn('id', array_unique($ids))->where('is_active', true)->orderBy('name')->get();
    }

    private function teamOptions(User $user): Collection
    {
        $ownerIds = $this->ownerOptions($user)->pluck('id')->all();
        return Team::where('is_active', true)->whereHas('members', fn ($query) => $query->whereIn('users.id', $ownerIds))->orderBy('name')->get();
    }

    private function masterValues(string $type): Collection
    {
        return CrmMasterValue::where('type', $type)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }
}
