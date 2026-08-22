<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\CrmMasterValue;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MissedCallAutomationService
{
    public function __construct(
        private CallLogMatcher $matcher,
        private CrmNotificationService $notifications,
        private AuditService $audit,
    ) {
    }

    public function handle(CallLog $callLog, array $event): void
    {
        if (($event['direction'] ?? null) !== 'inbound') {
            return;
        }

        $eventType = $event['event_type'] ?? null;

        if (in_array($eventType, ['call.answered', 'call.completed'], true)) {
            $this->resolveFalseMissedCall($callLog, 'Later answered/completed event received.');

            return;
        }

        if ($eventType !== 'call.missed') {
            return;
        }

        if ($callLog->answered_at || $callLog->status === 'completed') {
            $this->skip($callLog, 'resolved_answered', 'Answered/completed call state already exists.');

            return;
        }

        if ($callLog->missed_call_activity_id || $callLog->missed_call_automation_status === 'created') {
            return;
        }

        $agent = $callLog->user;
        if (! $agent || ! $agent->is_active) {
            $this->skip($callLog, 'skipped_unmapped', 'No active mapped CRM agent found.');

            return;
        }

        $match = $this->matcher->resolve($callLog->customer_number_normalized);
        if (($match['state'] ?? null) === 'ambiguous') {
            $this->skip($callLog, 'skipped_ambiguous', 'Multiple CRM records match caller phone.');

            return;
        }

        if (($match['state'] ?? null) !== 'matched') {
            $this->skip($callLog, 'skipped_unknown', 'No CRM record matches caller phone.');

            return;
        }

        $callLog->forceFill($match['ids'])->save();
        $callLog->refresh();

        $related = $this->relatedRecord($callLog);
        if (! $related || ! $this->recordVisibleTo($related, $agent)) {
            $this->skip($callLog, 'skipped_restricted', 'Mapped agent cannot access matched CRM record.');

            return;
        }

        $activity = Activity::create([
            'activity_type_id' => $this->followUpType()->id,
            'subject' => 'Missed Call',
            'description' => $this->description($callLog, $related),
            'related_type' => $related::class,
            'related_id' => $related->getKey(),
            'assigned_user_id' => $agent->id,
            'created_by_id' => $agent->id,
            'updated_by_id' => $agent->id,
            'priority' => 'high',
            'status' => 'pending',
            'due_at' => now()->addHour()->startOfMinute(),
        ]);

        $callLog->forceFill([
            'missed_call_activity_id' => $activity->id,
            'missed_call_automation_status' => 'created',
            'missed_call_automated_at' => now(),
            'missed_call_automation_reason' => null,
        ])->save();

        $this->notifications->notifyMissedCallFollowUp($activity, $callLog->refresh());

        $this->audit->log('missed_call_automation.created', $callLog, 'Missed-call follow-up created.', null, [
            'activity_id' => $activity->id,
            'assigned_user_id' => $agent->id,
            'related_type' => $related::class,
            'related_id' => $related->getKey(),
            'caller_phone' => $this->safePhone($callLog),
        ]);
    }

    private function resolveFalseMissedCall(CallLog $callLog, string $reason): void
    {
        if (! $callLog->missed_call_activity_id && ! in_array($callLog->missed_call_automation_status, ['created', 'resolved_answered'], true)) {
            return;
        }

        if ($callLog->missedCallActivity && $callLog->missedCallActivity->status === 'pending') {
            $callLog->missedCallActivity->forceFill([
                'status' => 'cancelled',
                'outcome' => 'Resolved by later answered/completed call event.',
            ])->save();
        }

        $this->skip($callLog, 'resolved_answered', $reason, 'missed_call_automation.resolved');
    }

    private function skip(CallLog $callLog, string $status, string $reason, string $action = 'missed_call_automation.skipped'): void
    {
        if ($callLog->missed_call_automation_status === 'created' && $status !== 'resolved_answered') {
            return;
        }

        $callLog->forceFill([
            'missed_call_automation_status' => $status,
            'missed_call_automation_reason' => $reason,
        ])->save();

        $this->audit->log($action, $callLog, $reason, null, [
            'status' => $status,
            'caller_phone' => $this->safePhone($callLog),
        ]);
    }

    private function followUpType(): CrmMasterValue
    {
        return CrmMasterValue::firstOrCreate([
            'type' => 'activity_type',
            'slug' => CrmMasterValue::makeSlug('Follow-up'),
        ], [
            'name' => 'Follow-up',
            'is_active' => true,
            'sort_order' => 4,
        ]);
    }

    private function relatedRecord(CallLog $callLog): ?Model
    {
        return $callLog->contact ?: $callLog->customer ?: $callLog->lead;
    }

    private function recordVisibleTo(Model $record, User $user): bool
    {
        if (! method_exists($record, 'scopeVisibleTo')) {
            return false;
        }

        return $record::query()->whereKey($record->getKey())->visibleTo($user)->exists();
    }

    private function description(CallLog $callLog, Model $related): string
    {
        $time = optional($callLog->occurredAt())->format('Y-m-d H:i') ?: now()->format('Y-m-d H:i');
        $name = match (true) {
            $related instanceof Contact => $related->name,
            $related instanceof Customer => $related->name,
            $related instanceof Lead => $related->name,
            default => 'CRM record',
        };

        return 'Missed inbound call from '.$this->safePhone($callLog).' at '.$time.'. Related record: '.$name.'.';
    }

    private function safePhone(CallLog $callLog): string
    {
        $phone = $callLog->customer_number ?: $callLog->from_number;

        return $phone ? app(PhonePrivacyService::class)->mask($phone) : 'Unknown caller';
    }
}
