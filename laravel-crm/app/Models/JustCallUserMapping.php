<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JustCallUserMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'justcall_user_id',
        'active_user_id',
        'active_justcall_user_id',
        'justcall_name',
        'justcall_email',
        'justcall_phone',
        'is_active',
        'last_synced_at',
        'last_verified_at',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'active_user_id' => 'integer',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'created_by_id' => 'integer',
        'updated_by_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
