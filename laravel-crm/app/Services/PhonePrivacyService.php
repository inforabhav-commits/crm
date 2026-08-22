<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\User;

class PhonePrivacyService
{
    public function canViewFullPhone(User $user): bool
    {
        return $user->hasRole('super-admin')
            || $user->hasRole('admin')
            || $user->can('customers.view_full_phone');
    }

    public function display(?string $phone, User $user): string
    {
        if (! filled($phone)) {
            return '-';
        }

        if ($this->canViewFullPhone($user)) {
            return (string) $phone;
        }

        return $this->mask($phone);
    }

    public function mask(?string $phone): string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return '-';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return 'XXXX';
        }

        $lastFour = substr($digits, -4);
        $maskLength = max(0, strlen($digits) - 4);

        return str_repeat('X', $maskLength).$lastFour;
    }

    public function editableValue(?string $phone, User $user): string
    {
        return $this->canViewFullPhone($user) ? (string) ($phone ?? '') : '';
    }

    public function editablePlaceholder(?string $phone, User $user): string
    {
        return $this->canViewFullPhone($user) ? '' : $this->mask($phone);
    }

    public function callNumber(CallLog $callLog, User $user): string
    {
        $phone = $callLog->customer_number
            ?: ($callLog->direction === 'inbound' ? $callLog->from_number : $callLog->to_number)
            ?: $callLog->customer_number_normalized;

        return $this->display($phone, $user);
    }

    public function exportValue(?string $phone, User $user): string
    {
        return $this->canViewFullPhone($user) ? (string) ($phone ?? '') : $this->mask($phone);
    }
}
