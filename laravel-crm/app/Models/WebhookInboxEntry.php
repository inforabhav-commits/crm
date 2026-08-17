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

    public function toArray(): array
    {
        $data = parent::toArray();

        if (isset($data['payload'])) {
            $data['payload'] = $this->redactSensitiveKeys($data['payload']);
        }

        if (isset($data['normalized_payload'])) {
            $data['normalized_payload'] = $this->redactSensitiveKeys($data['normalized_payload']);
        }

        return $data;
    }

    private function redactSensitiveKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $lowerKey = strtolower((string) $key);

            if (str_contains($lowerKey, 'secret') || str_contains($lowerKey, 'signature') || str_contains($lowerKey, 'token') || str_contains($lowerKey, 'api_key') || str_contains($lowerKey, 'authorization')) {
                $value[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($item)) {
                $value[$key] = $this->redactSensitiveKeys($item);
            }
        }

        return $value;
    }
}
