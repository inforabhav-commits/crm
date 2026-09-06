<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CustomerDateFilter
{
    public function apply(Builder $query, Request $request): void
    {
        $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from_date') ? 'after_or_equal:from_date' : null]),
        ]);

        if ($request->filled('from_date')) {
            $query->where('customers.created_at', '>=', $request->date('from_date')->startOfDay());
        }
        if ($request->filled('to_date')) {
            $query->where('customers.created_at', '<', $request->date('to_date')->addDay()->startOfDay());
        }
    }
}
