<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CrmMasterValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'slug',
        'description',
        'color',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function makeSlug(string $name): string
    {
        return Str::slug($name);
    }
}
