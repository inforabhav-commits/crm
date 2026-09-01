<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_customer_id',
        'name',
        'company',
        'email',
        'phone',
        'website',
        'industry',
        'address',
        'sale_date',
        'amount',
        'plan',
        'software',
        'license_number',
        'product_number',
        'file_password',
        'cloud_customer',
        'customer_user_id',
        'customer_password',
        'issue',
        'sale_type',
        'no_of_cases',
        'payment_type',
        'last_4',
        'card_type',
        'end',
        'notes',
        'owner_id',
        'converted_from_lead_id',
        'is_active',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        'converted_from_lead_id' => 'integer',
        'file_password' => 'encrypted',
        'customer_password' => 'encrypted',
        'is_active' => 'boolean',
        'created_by_id' => 'integer',
        'updated_by_id' => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function convertedFromLead()
    {
        return $this->belongsTo(Lead::class, 'converted_from_lead_id');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
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
