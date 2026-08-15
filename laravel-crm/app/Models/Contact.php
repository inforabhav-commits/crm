<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'source_lead_id',
        'first_name',
        'last_name',
        'title',
        'email',
        'phone',
        'mobile',
        'is_primary',
        'is_active',
        'notes',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'source_lead_id' => 'integer',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'created_by_id' => 'integer',
        'updated_by_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sourceLead()
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'related')->latest('due_at');
    }

    public function callLogs()
    {
        return $this->hasMany(CallLog::class)->latest('last_event_at');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('customer', function (Builder $customerQuery) use ($user) {
            $customerQuery->visibleTo($user);
        });
    }
}
