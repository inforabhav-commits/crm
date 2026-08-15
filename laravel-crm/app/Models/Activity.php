<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    public const STATUSES = ['pending', 'completed', 'cancelled'];

    protected $fillable = [
        'activity_type_id',
        'subject',
        'description',
        'related_type',
        'related_id',
        'assigned_user_id',
        'created_by_id',
        'updated_by_id',
        'priority',
        'status',
        'due_at',
        'reminder_at',
        'completed_at',
        'outcome',
        'completion_notes',
        'next_action',
    ];

    protected $casts = [
        'activity_type_id' => 'integer',
        'related_id' => 'integer',
        'assigned_user_id' => 'integer',
        'created_by_id' => 'integer',
        'updated_by_id' => 'integer',
        'due_at' => 'datetime',
        'reminder_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function type()
    {
        return $this->belongsTo(CrmMasterValue::class, 'activity_type_id');
    }

    public function related()
    {
        return $this->morphTo();
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return $query;
        }

        $visibleUserIds = $user->reportingTreeUserIds();
        $visibleUserIds[] = $user->id;
        $visibleUserIds = array_unique($visibleUserIds);

        return $query->where(function (Builder $query) use ($user, $visibleUserIds) {
            $query->whereIn('assigned_user_id', $visibleUserIds)
                ->orWhereIn('created_by_id', $visibleUserIds)
                ->orWhereHasMorph('related', [Lead::class, Customer::class, Contact::class, Opportunity::class], function (Builder $relatedQuery, string $type) use ($user) {
                    if (method_exists($type, 'scopeVisibleTo')) {
                        $relatedQuery->visibleTo($user);
                    }
                });
        });
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->due_at && $this->due_at->isPast();
    }
}
