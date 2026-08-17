<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    use HasFactory;

    public const DISPOSITIONS = [
        'Connected',
        'No Answer',
        'Busy',
        'Callback Requested',
        'Interested',
        'Not Interested',
        'Wrong Number',
        'Voicemail',
        'Other',
    ];

    protected $fillable = [
        'provider',
        'external_call_id',
        'external_event_id',
        'direction',
        'status',
        'user_id',
        'agent_external_id',
        'from_number',
        'to_number',
        'customer_number',
        'customer_number_normalized',
        'lead_id',
        'customer_id',
        'contact_id',
        'opportunity_id',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'disposition',
        'provider_disposition',
        'crm_disposition',
        'notes',
        'provider_notes',
        'crm_notes',
        'follow_up_required',
        'follow_up_activity_id',
        'recording_reference',
        'recording_url',
        'recording_status',
        'recording_duration_seconds',
        'recording_fetched_at',
        'screen_pop_dismissed_at',
        'screen_pop_expires_at',
        'review_status',
        'reviewed_at',
        'reconciled_by_id',
        'reconciliation_notes',
        'missed_call_automation_status',
        'missed_call_activity_id',
        'missed_call_automated_at',
        'missed_call_automation_reason',
        'last_event_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'lead_id' => 'integer',
        'customer_id' => 'integer',
        'contact_id' => 'integer',
        'opportunity_id' => 'integer',
        'follow_up_required' => 'boolean',
        'follow_up_activity_id' => 'integer',
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'recording_duration_seconds' => 'integer',
        'recording_fetched_at' => 'datetime',
        'screen_pop_dismissed_at' => 'datetime',
        'screen_pop_expires_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'reconciled_by_id' => 'integer',
        'missed_call_activity_id' => 'integer',
        'missed_call_automated_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function followUpActivity()
    {
        return $this->belongsTo(Activity::class, 'follow_up_activity_id');
    }

    public function missedCallActivity()
    {
        return $this->belongsTo(Activity::class, 'missed_call_activity_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return $query;
        }

        $visibleUserIds = $user->reportingTreeUserIds();
        $visibleUserIds[] = $user->id;
        $visibleUserIds = array_unique($visibleUserIds);

        return $query->where(function (Builder $visibleQuery) use ($user, $visibleUserIds) {
            $visibleQuery->where(function (Builder $unmatchedQuery) use ($visibleUserIds) {
                $unmatchedQuery->whereIn('user_id', $visibleUserIds)
                    ->whereNull('lead_id')
                    ->whereNull('customer_id')
                    ->whereNull('contact_id')
                    ->whereNull('opportunity_id');
            })
                ->orWhereHas('lead', fn (Builder $leadQuery) => $leadQuery->visibleTo($user))
                ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery->visibleTo($user))
                ->orWhereHas('contact', fn (Builder $contactQuery) => $contactQuery->visibleTo($user))
                ->orWhereHas('opportunity', fn (Builder $opportunityQuery) => $opportunityQuery->visibleTo($user));
        });
    }

    public function occurredAt()
    {
        return $this->started_at ?: $this->answered_at ?: $this->ended_at ?: $this->last_event_at ?: $this->created_at;
    }

    public function hasAvailableRecording(): bool
    {
        return $this->recording_status === 'available' && filled($this->recording_url ?: $this->recording_reference);
    }

    public function relatedRecord(): ?Model
    {
        return $this->contact ?: $this->customer ?: $this->lead ?: $this->opportunity;
    }
}
