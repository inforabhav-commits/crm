<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadAssignmentHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'lead_id',
        'assigned_by_id',
        'assigned_from_id',
        'assigned_to_id',
        'team_id',
        'method',
        'assigned_at',
    ];

    protected $casts = [
        'lead_id' => 'integer',
        'assigned_by_id' => 'integer',
        'assigned_from_id' => 'integer',
        'assigned_to_id' => 'integer',
        'team_id' => 'integer',
        'assigned_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function assignedFrom()
    {
        return $this->belongsTo(User::class, 'assigned_from_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
