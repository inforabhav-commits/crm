<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'entity_type',
        'trigger',
        'conditions',
        'action',
        'is_active',
        'priority',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'conditions' => 'array',
        'action' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function executions()
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
