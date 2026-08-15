<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'customer_id',
        'contact_id',
        'source_lead_id',
        'stage_id',
        'owner_id',
        'amount',
        'currency',
        'probability',
        'status',
        'expected_close_date',
        'next_step',
        'description',
        'loss_reason_id',
        'closed_at',
        'won_at',
        'lost_at',
        'notes',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'contact_id' => 'integer',
        'source_lead_id' => 'integer',
        'stage_id' => 'integer',
        'owner_id' => 'integer',
        'amount' => 'decimal:2',
        'probability' => 'integer',
        'expected_close_date' => 'date',
        'loss_reason_id' => 'integer',
        'closed_at' => 'datetime',
        'won_at' => 'datetime',
        'lost_at' => 'datetime',
        'created_by_id' => 'integer',
        'updated_by_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function sourceLead()
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    public function stage()
    {
        return $this->belongsTo(CrmMasterValue::class, 'stage_id');
    }

    public function lossReason()
    {
        return $this->belongsTo(CrmMasterValue::class, 'loss_reason_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'related')->latest('due_at');
    }

    public function callLogs()
    {
        return $this->hasMany(CallLog::class)->latest('last_event_at');
    }

    public function stageHistories()
    {
        return $this->hasMany(OpportunityStageHistory::class)->latest('changed_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return $query;
        }

        $visibleUserIds = $user->reportingTreeUserIds();
        $visibleUserIds[] = $user->id;

        return $query->whereIn('owner_id', array_unique($visibleUserIds));
    }
}
