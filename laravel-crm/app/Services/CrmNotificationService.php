<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\Opportunity;
use App\Models\OpportunityStageHistory;
use App\Models\User;
use App\Notifications\CrmDatabaseNotification;

class CrmNotificationService
{
    public function notifyLeadAssignment(LeadAssignmentHistory $history): void
    {
        $lead = $history->lead;
        $recipient = $history->assignedTo;

        if (! $lead || ! $recipient || ! $recipient->is_active || ! $this->leadVisibleTo($lead, $recipient)) {
            return;
        }

        $isReassignment = $history->assigned_from_id && (int) $history->assigned_from_id !== (int) $history->assigned_to_id;
        $this->notifyOnce($recipient, 'lead-assignment:'.$history->id, [
            'category' => $isReassignment ? 'lead_reassigned' : 'lead_assigned',
            'title' => $isReassignment ? 'Lead reassigned to you' : 'New lead assigned to you',
            'message' => $lead->name,
            'record_type' => Lead::class,
            'record_id' => $lead->id,
            'url' => route('leads.show', $lead),
            'actor_id' => $history->assigned_by_id,
            'key' => 'lead-assignment:'.$history->id,
        ]);
    }

    public function notifyOpportunityStageChange(OpportunityStageHistory $history): void
    {
        $opportunity = $history->opportunity;
        $recipient = $opportunity?->owner;

        if (! $opportunity || ! $recipient || ! $recipient->is_active || ! $this->opportunityVisibleTo($opportunity, $recipient)) {
            return;
        }

        $stage = $history->toStage?->name ?? 'new stage';
        $this->notifyOnce($recipient, 'opportunity-stage:'.$history->id, [
            'category' => 'opportunity_stage_changed',
            'title' => 'Opportunity stage changed',
            'message' => $opportunity->name.' moved to '.$stage,
            'record_type' => Opportunity::class,
            'record_id' => $opportunity->id,
            'url' => route('opportunities.show', $opportunity),
            'actor_id' => $history->changed_by_id,
            'key' => 'opportunity-stage:'.$history->id,
        ]);
    }

    public function syncDueActivityNotificationsFor(User $user): void
    {
        Activity::with('related')
            ->where('assigned_user_id', $user->id)
            ->where('status', 'pending')
            ->whereDate('due_at', today())
            ->get()
            ->each(fn (Activity $activity) => $this->notifyActivity($activity, $user, 'activity-due:'.$activity->id.':'.today()->toDateString(), 'activity_due', 'Activity due today'));

        Activity::with('related')
            ->where('assigned_user_id', $user->id)
            ->where('status', 'pending')
            ->where('due_at', '<', today())
            ->get()
            ->each(function (Activity $activity) use ($user) {
                app(WorkflowRuleService::class)->dispatch($activity, 'activity_overdue', ['event_key' => 'activity-overdue:'.$activity->id.':'.today()->toDateString()], $user);
                $this->notifyActivity($activity, $user, 'activity-overdue:'.$activity->id.':'.today()->toDateString(), 'activity_overdue', 'Activity overdue');
            });
    }

    public function notifyMissedCallFollowUp(Activity $activity, CallLog $callLog): void
    {
        $recipient = $activity->assignedUser;

        if (! $recipient || ! $recipient->is_active || ! Activity::whereKey($activity->id)->visibleTo($recipient)->exists()) {
            return;
        }

        $caller = $callLog->customer_number ?: $callLog->from_number ?: 'Unknown caller';

        $this->notifyOnce($recipient, 'missed-call:'.$callLog->id, [
            'category' => 'missed_call_follow_up',
            'title' => 'Missed call follow-up',
            'message' => 'Missed call from '.$caller,
            'record_type' => Activity::class,
            'record_id' => $activity->id,
            'url' => $this->relatedUrl($activity->related) ?: route('activities.show', $activity),
            'actor_id' => null,
            'key' => 'missed-call:'.$callLog->id,
        ]);
    }

    private function notifyActivity(Activity $activity, User $recipient, string $key, string $category, string $title): void
    {
        if (! $recipient->is_active || ! Activity::whereKey($activity->id)->visibleTo($recipient)->exists()) {
            return;
        }

        $this->notifyOnce($recipient, $key, [
            'category' => $category,
            'title' => $title,
            'message' => $activity->subject,
            'record_type' => Activity::class,
            'record_id' => $activity->id,
            'url' => $this->relatedUrl($activity->related) ?: route('activities.show', $activity),
            'key' => $key,
        ]);
    }

    private function notifyOnce(User $user, string $key, array $payload): void
    {
        $exists = $user->notifications()
            ->where('type', CrmDatabaseNotification::class)
            ->where('data->key', $key)
            ->exists();

        if (! $exists) {
            $user->notify(new CrmDatabaseNotification($payload));
        }
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

    private function leadVisibleTo(Lead $lead, User $user): bool
    {
        return Lead::whereKey($lead->id)->visibleTo($user)->exists();
    }

    private function opportunityVisibleTo(Opportunity $opportunity, User $user): bool
    {
        return Opportunity::whereKey($opportunity->id)->visibleTo($user)->exists();
    }
}
