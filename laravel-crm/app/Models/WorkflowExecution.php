<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkflowExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_rule_id',
        'event_key',
        'status',
        'error_summary',
        'executed_at',
    ];

    protected $casts = [
        'workflow_rule_id' => 'integer',
        'executed_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(WorkflowRule::class, 'workflow_rule_id');
    }
}
