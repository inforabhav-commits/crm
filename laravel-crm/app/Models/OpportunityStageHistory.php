<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpportunityStageHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'opportunity_id',
        'from_stage_id',
        'to_stage_id',
        'changed_by_id',
        'notes',
        'changed_at',
    ];

    protected $casts = [
        'opportunity_id' => 'integer',
        'from_stage_id' => 'integer',
        'to_stage_id' => 'integer',
        'changed_by_id' => 'integer',
        'changed_at' => 'datetime',
    ];

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function fromStage()
    {
        return $this->belongsTo(CrmMasterValue::class, 'from_stage_id');
    }

    public function toStage()
    {
        return $this->belongsTo(CrmMasterValue::class, 'to_stage_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
