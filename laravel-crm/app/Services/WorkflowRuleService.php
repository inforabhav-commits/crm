<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CrmMasterValue;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkflowExecution;
use App\Models\WorkflowRule;
use App\Notifications\CrmDatabaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class WorkflowRuleService
{
    private static int $depth = 0;

    public function dispatch(Model $record, string $trigger, array $context = [], ?User $actor = null): void
    {
        if (self::$depth > 0 || ! $record->exists) {
            return;
        }

        $entityType = $record::class;
        $eventKey = (string) ($context['event_key'] ?? $trigger.':'.$record->getKey().':'.($record->updated_at?->format('U.u') ?? now()->format('U.u')));
        $rules = WorkflowRule::query()
            ->where('entity_type', $entityType)
            ->where('trigger', $trigger)
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        self::$depth++;
        try {
            foreach ($rules as $rule) {
                if (! $this->conditionsMatch($rule->conditions ?? [], $record, $context)) {
                    continue;
                }

                $execution = WorkflowExecution::firstOrCreate([
                    'workflow_rule_id' => $rule->id,
                    'event_key' => Str::limit($eventKey, 190, ''),
                ], ['status' => 'running']);

                if (! $execution->wasRecentlyCreated) {
                    continue;
                }

                try {
                    $this->executeAction($rule->action ?? [], $record, $context, $actor);
                    $execution->forceFill(['status' => 'succeeded', 'executed_at' => now(), 'error_summary' => null])->save();
                } catch (Throwable $exception) {
                    $execution->forceFill(['status' => 'failed', 'executed_at' => now(), 'error_summary' => Str::limit($exception->getMessage(), 500, '')])->save();
                }
            }
        } finally {
            self::$depth--;
        }
    }

    private function conditionsMatch(array $conditions, Model $record, array $context): bool
    {
        foreach ($conditions as $field => $expected) {
            $actual = match ($field) {
                'status' => $record instanceof Opportunity ? $record->status : ($record instanceof Lead ? $record->status?->slug : null),
                'lead_status' => $record instanceof Lead ? $record->status?->slug : null,
                'lead_source' => $record instanceof Lead ? $record->source?->slug : null,
                'opportunity_stage' => $record instanceof Opportunity ? $record->stage?->slug : null,
                'priority' => $record->priority ?? null,
                'owner_id' => $record->owner_id ?? null,
                'team_id' => $this->recordTeamId($record),
                'previous_status' => $context['previous_status'] ?? null,
                'previous_stage' => $context['previous_stage'] ?? null,
                default => data_get($record, $field),
            };

            if ((string) $actual !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    private function executeAction(array $action, Model $record, array $context, ?User $actor): void
    {
        $type = $action['type'] ?? '';
        if (! in_array($type, ['create_activity', 'notify', 'update_field', 'assign'], true)) {
            throw new \InvalidArgumentException('Unsupported workflow action.');
        }

        if ($type === 'create_activity') {
            $activityType = CrmMasterValue::where('type', 'activity_type')->where('slug', $action['activity_type'] ?? 'follow-up')->where('is_active', true)->first();
            if (! $activityType) {
                throw new \RuntimeException('Workflow activity type is not active.');
            }
            $assignedId = (int) ($action['assigned_user_id'] ?? $record->owner_id ?? $actor?->id ?? 0);
            if (! User::whereKey($assignedId)->where('is_active', true)->exists()) {
                throw new \RuntimeException('Workflow activity assignee is invalid.');
            }
            $priority = $action['priority'] ?? 'normal';
            Activity::create([
                'activity_type_id' => $activityType->id,
                'subject' => Str::limit((string) ($action['subject'] ?? 'Workflow follow-up'), 255, ''),
                'description' => $action['description'] ?? null,
                'related_type' => $record::class,
                'related_id' => $record->getKey(),
                'assigned_user_id' => $assignedId,
                'created_by_id' => $actor?->id,
                'updated_by_id' => $actor?->id,
                'priority' => in_array($priority, Activity::PRIORITIES, true) ? $priority : 'normal',
                'status' => 'pending',
                'due_at' => now()->addHours(max(1, (int) ($action['due_in_hours'] ?? 24))),
            ]);
            return;
        }

        if ($type === 'notify') {
            $recipientId = (int) ($action['recipient_user_id'] ?? $record->owner_id ?? $actor?->id ?? 0);
            $recipient = User::whereKey($recipientId)->where('is_active', true)->first();
            if (! $recipient) throw new \RuntimeException('Workflow notification recipient is invalid.');
            $recipient->notify(new CrmDatabaseNotification([
                'category' => 'workflow',
                'title' => Str::limit((string) ($action['title'] ?? 'Workflow notification'), 255, ''),
                'message' => Str::limit((string) ($action['message'] ?? $record->getAttribute('name') ?? 'CRM workflow action'), 2000, ''),
                'record_type' => $record::class,
                'record_id' => $record->getKey(),
                'url' => $this->recordUrl($record),
                'actor_id' => $actor?->id,
                'key' => 'workflow:'.$record::class.':'.$record->getKey().':'.md5(json_encode($action)),
            ]));
            return;
        }

        if ($type === 'update_field') {
            $allowed = $record instanceof Lead ? ['priority', 'lead_status_id', 'next_follow_up_at'] : ($record instanceof Opportunity ? ['stage_id', 'status', 'next_step'] : []);
            $field = (string) ($action['field'] ?? '');
            if (! in_array($field, $allowed, true)) throw new \RuntimeException('Workflow field update is not permitted.');
            $record->forceFill([$field => $action['value'] ?? null, 'updated_by_id' => $actor?->id ?: $record->updated_by_id])->save();
            $this->dispatch($record->refresh(), 'record_updated', ['event_key' => 'workflow-update:'.$record::class.':'.$record->id.':'.$field], $actor);
            return;
        }

        if (! $record instanceof Lead || ! $actor?->can('leads.assign')) {
            throw new \RuntimeException('Workflow assignment is not permitted.');
        }
        $assignee = User::whereKey($action['user_id'] ?? 0)->where('is_active', true)->first();
        if (! $assignee) throw new \RuntimeException('Workflow assignee is invalid.');
        app(LeadAssignmentService::class)->assignToUser($record, $assignee, $actor, 'workflow');
        $this->dispatch($record->refresh(), 'record_updated', ['event_key' => 'workflow-assign:'.$record::class.':'.$record->id.':'.$assignee->id], $actor);
    }

    private function recordTeamId(Model $record): ?int
    {
        $ownerId = $record->owner_id ?? null;
        return $ownerId ? Team::whereHas('members', fn ($query) => $query->whereKey($ownerId))->value('id') : null;
    }

    private function recordUrl(Model $record): string
    {
        return match (true) {
            $record instanceof Lead => route('leads.show', $record),
            $record instanceof Opportunity => route('opportunities.show', $record),
            default => route('dashboard'),
        };
    }
}
