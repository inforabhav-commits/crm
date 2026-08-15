<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'lead_status_id',
        'lead_source_id',
        'owner_id',
        'priority',
        'next_follow_up_at',
        'notes',
        'qualification_status',
        'qualification_need',
        'qualification_budget',
        'qualification_authority',
        'qualification_timeline',
        'qualification_interest_level',
        'qualification_notes',
        'qualified_at',
        'qualified_by_id',
        'converted_at',
        'converted_by_id',
        'converted_customer_id',
        'converted_contact_id',
        'converted_opportunity_id',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'lead_status_id' => 'integer',
        'lead_source_id' => 'integer',
        'owner_id' => 'integer',
        'created_by_id' => 'integer',
        'updated_by_id' => 'integer',
        'next_follow_up_at' => 'datetime',
        'qualification_budget' => 'decimal:2',
        'qualified_at' => 'datetime',
        'converted_at' => 'datetime',
        'qualified_by_id' => 'integer',
        'converted_by_id' => 'integer',
        'converted_customer_id' => 'integer',
        'converted_contact_id' => 'integer',
        'converted_opportunity_id' => 'integer',
    ];

    public function status()
    {
        return $this->belongsTo(CrmMasterValue::class, 'lead_status_id');
    }

    public function source()
    {
        return $this->belongsTo(CrmMasterValue::class, 'lead_source_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function qualifiedBy()
    {
        return $this->belongsTo(User::class, 'qualified_by_id');
    }

    public function convertedBy()
    {
        return $this->belongsTo(User::class, 'converted_by_id');
    }

    public function convertedCustomer()
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function convertedContact()
    {
        return $this->belongsTo(Contact::class, 'converted_contact_id');
    }

    public function convertedOpportunity()
    {
        return $this->belongsTo(Opportunity::class, 'converted_opportunity_id');
    }

    public function assignmentHistories()
    {
        return $this->hasMany(LeadAssignmentHistory::class)->latest('assigned_at');
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'related')->latest('due_at');
    }

    public function callLogs()
    {
        return $this->hasMany(CallLog::class)->latest('last_event_at');
    }

    public function isQualifiedForConversion(): bool
    {
        return $this->qualification_status === 'qualified'
            && $this->qualified_at !== null
            && filled($this->qualification_need)
            && filled($this->qualification_authority)
            && filled($this->qualification_timeline)
            && filled($this->qualification_interest_level);
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
