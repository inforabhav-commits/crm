<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use App\Services\CrmNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CrmNotificationService $notifications)
    {
        $user = $request->user();
        $notifications->syncDueActivityNotificationsFor($user);
        $filters = $request->only(['owner', 'team', 'date', 'activity_status']);
        $date = ! empty($filters['date']) ? Carbon::parse($filters['date'])->startOfDay() : today();
        $ownerIds = $this->filteredOwnerIds($user, $filters);

        $leadQuery = $this->leadQuery($user, $ownerIds);
        $activityQuery = $this->activityQuery($user, $ownerIds, $filters['activity_status'] ?? null);
        $opportunityQuery = $this->opportunityQuery($user, $ownerIds);

        $todayActivities = (clone $activityQuery)
            ->with(['type', 'assignedUser', 'related'])
            ->whereDate('due_at', $date)
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $overdueActivities = (clone $activityQuery)
            ->with(['type', 'assignedUser', 'related'])
            ->where('status', 'pending')
            ->where('due_at', '<', $date)
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $upcomingActivities = (clone $activityQuery)
            ->with(['type', 'assignedUser', 'related'])
            ->where('status', 'pending')
            ->where('due_at', '>', $date->copy()->endOfDay())
            ->orderBy('due_at')
            ->limit(8)
            ->get();

        $openOpportunities = (clone $opportunityQuery)
            ->with(['customer', 'owner', 'stage'])
            ->where('status', 'open')
            ->orderBy('expected_close_date')
            ->limit(8)
            ->get();

        $recentlyAssignedLeads = LeadAssignmentHistory::with(['lead.owner', 'assignedTo', 'assignedBy'])
            ->whereHas('lead', function (Builder $query) use ($user, $ownerIds) {
                $query->visibleTo($user);
                $this->applyOwnerFilter($query, $ownerIds);
            })
            ->latest('assigned_at')
            ->limit(8)
            ->get();

        return view('dashboard', [
            'filters' => $filters,
            'selectedDate' => $date,
            'owners' => $this->ownerOptions($user),
            'teams' => $this->teamOptions($user),
            'activityStatuses' => Activity::STATUSES,
            'kpis' => [
                'my_leads' => (clone $leadQuery)->count(),
                'new_leads' => (clone $leadQuery)->whereHas('status', fn ($query) => $query->where('slug', 'new'))->count(),
                'leads_requiring_follow_up' => (clone $leadQuery)->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', $date->copy()->endOfDay())->count(),
                'today_activities' => (clone $activityQuery)->whereDate('due_at', $date)->count(),
                'overdue_activities' => (clone $activityQuery)->where('status', 'pending')->where('due_at', '<', $date)->count(),
                'upcoming_follow_ups' => (clone $activityQuery)->where('status', 'pending')->where('due_at', '>', $date->copy()->endOfDay())->count(),
                'open_opportunities' => (clone $opportunityQuery)->where('status', 'open')->count(),
                'pipeline_value' => (clone $opportunityQuery)->where('status', 'open')->sum('amount'),
            ],
            'todayActivities' => $todayActivities,
            'overdueActivities' => $overdueActivities,
            'upcomingActivities' => $upcomingActivities,
            'openOpportunities' => $openOpportunities,
            'recentlyAssignedLeads' => $recentlyAssignedLeads,
            'recentCrmActivity' => $this->recentCrmActivity($activityQuery),
            'workQueue' => $this->workQueue($leadQuery, $activityQuery, $opportunityQuery, $date),
        ]);
    }

    private function workQueue(Builder $leadQuery, Builder $activityQuery, Builder $opportunityQuery, $date): Collection
    {
        $items = collect();

        (clone $activityQuery)->with(['related', 'assignedUser'])->where('status', 'pending')->where('due_at', '<', $date)->limit(20)->get()
            ->each(fn (Activity $activity) => $items->push($this->activityQueueItem($activity, 'Overdue', 1)));

        (clone $activityQuery)->with(['related', 'assignedUser'])->where('status', 'pending')->whereDate('due_at', $date)->limit(20)->get()
            ->each(fn (Activity $activity) => $items->push($this->activityQueueItem($activity, 'Due Today', 2)));

        (clone $leadQuery)->with(['owner', 'activities'])->whereIn('priority', ['high', 'urgent'])->limit(20)->get()
            ->each(fn (Lead $lead) => $items->push([
                'rank' => $lead->priority === 'urgent' ? 2 : 3,
                'due_at' => $lead->next_follow_up_at,
                'priority' => $lead->priority,
                'label' => 'High Priority Lead',
                'title' => $lead->name,
                'owner' => $lead->owner?->name,
                'url' => route('leads.show', $lead),
            ]));

        (clone $leadQuery)->with('owner')->whereDoesntHave('activities', fn ($query) => $query->where('created_at', '>=', now()->subDays(7)))->limit(20)->get()
            ->each(fn (Lead $lead) => $items->push([
                'rank' => 4,
                'due_at' => $lead->next_follow_up_at,
                'priority' => $lead->priority,
                'label' => 'No Recent Activity',
                'title' => $lead->name,
                'owner' => $lead->owner?->name,
                'url' => route('leads.show', $lead),
            ]));

        (clone $opportunityQuery)->with(['customer', 'owner'])->where('status', 'open')->whereNotNull('next_step')->limit(20)->get()
            ->each(fn (Opportunity $opportunity) => $items->push([
                'rank' => 5,
                'due_at' => $opportunity->expected_close_date,
                'priority' => 'normal',
                'label' => 'Opportunity Next Action',
                'title' => $opportunity->name,
                'owner' => $opportunity->owner?->name,
                'url' => route('opportunities.show', $opportunity),
            ]));

        (clone $activityQuery)->with(['related', 'assignedUser'])->where('status', 'pending')->where('due_at', '>', $date->copy()->endOfDay())->limit(20)->get()
            ->each(fn (Activity $activity) => $items->push($this->activityQueueItem($activity, 'Upcoming', 6)));

        return $items->sortBy([
            ['rank', 'asc'],
            fn ($a, $b) => $this->priorityRank($a['priority']) <=> $this->priorityRank($b['priority']),
            fn ($a, $b) => (string) ($a['due_at'] ?? '') <=> (string) ($b['due_at'] ?? ''),
        ])->take(20)->values();
    }

    private function activityQueueItem(Activity $activity, string $label, int $rank): array
    {
        return [
            'rank' => $rank,
            'due_at' => $activity->due_at,
            'priority' => $activity->priority,
            'label' => $label,
            'title' => $activity->subject,
            'owner' => $activity->assignedUser?->name,
            'url' => $this->relatedUrl($activity->related) ?: route('activities.show', $activity),
        ];
    }

    private function recentCrmActivity(Builder $activityQuery): Collection
    {
        return (clone $activityQuery)
            ->with(['type', 'assignedUser', 'related'])
            ->latest()
            ->limit(8)
            ->get();
    }

    private function leadQuery(User $user, ?array $ownerIds): Builder
    {
        $query = Lead::with(['status', 'owner'])->visibleTo($user);
        $this->applyOwnerFilter($query, $ownerIds);

        return $query;
    }

    private function activityQuery(User $user, ?array $ownerIds, ?string $status): Builder
    {
        $query = Activity::visibleTo($user);
        if ($ownerIds !== null) {
            $query->whereIn('assigned_user_id', $ownerIds);
        }
        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    private function opportunityQuery(User $user, ?array $ownerIds): Builder
    {
        $query = Opportunity::with(['customer', 'owner'])->visibleTo($user);
        $this->applyOwnerFilter($query, $ownerIds);

        return $query;
    }

    private function applyOwnerFilter(Builder $query, ?array $ownerIds): void
    {
        if ($ownerIds !== null) {
            $query->whereIn('owner_id', $ownerIds);
        }
    }

    private function filteredOwnerIds(User $user, array $filters): ?array
    {
        $allowedIds = $this->ownerOptions($user)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! empty($filters['team'])) {
            $teamUserIds = Team::whereKey($filters['team'])->where('is_active', true)->first()?->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all() ?? [];
            $allowedIds = array_values(array_intersect($allowedIds, $teamUserIds));
        }

        if (! empty($filters['owner'])) {
            $ownerId = (int) $filters['owner'];
            return in_array($ownerId, $allowedIds, true) ? [$ownerId] : [];
        }

        return $allowedIds;
    }

    private function ownerOptions(User $user): Collection
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return User::where('is_active', true)->orderBy('name')->get();
        }

        $ids = $user->reportingTreeUserIds();
        $ids[] = $user->id;

        return User::whereIn('id', array_unique($ids))->where('is_active', true)->orderBy('name')->get();
    }

    private function teamOptions(User $user): Collection
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return Team::where('is_active', true)->orderBy('name')->get();
        }

        $ownerIds = $this->ownerOptions($user)->pluck('id')->all();

        return Team::where('is_active', true)
            ->whereHas('members', fn ($query) => $query->whereIn('users.id', $ownerIds))
            ->orderBy('name')
            ->get();
    }

    private function relatedUrl($related): ?string
    {
        if ($related instanceof Lead) {
            return route('leads.show', $related);
        }
        if ($related instanceof Customer) {
            return route('customers.show', $related);
        }
        if ($related instanceof Contact) {
            return route('contacts.show', $related);
        }
        if ($related instanceof Opportunity) {
            return route('opportunities.show', $related);
        }

        return null;
    }

    private function priorityRank(?string $priority): int
    {
        return match ($priority) {
            'urgent' => 1,
            'high' => 2,
            'normal' => 3,
            'low' => 4,
            default => 5,
        };
    }
}
