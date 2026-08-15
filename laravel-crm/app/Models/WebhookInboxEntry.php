<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookInboxEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'event_type',
        'external_id',
        'payload_hash',
        'payload',
        'normalized_payload',
        'normalized_event_type',
        'received_at',
        'processing_status',
        'processed_at',
        'normalized_at',
        'call_log_id',
        'failure_summary',
        'attempt_count',
    ];

    protected $casts = [
        'payload' => 'array',
        'normalized_payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'normalized_at' => 'datetime',
        'call_log_id' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function callLog()
    {
        return $this->belongsTo(CallLog::class);
    }
}
