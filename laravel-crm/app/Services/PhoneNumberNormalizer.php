<?php

namespace App\Services;

class PhoneNumberNormalizer
{
    public function normalize(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [
                'original' => null,
                'normalized' => null,
                'comparison' => null,
            ];
        }

        $original = trim((string) $value);
        if ($original === '') {
            return [
                'original' => null,
                'normalized' => null,
                'comparison' => null,
            ];
        }

        $hasLeadingPlus = str_starts_with($original, '+');
        $digits = preg_replace('/\D+/', '', $original) ?: '';

        return [
            'original' => $original,
            'normalized' => $digits === '' ? null : ($hasLeadingPlus ? '+'.$digits : $digits),
            'comparison' => $digits === '' ? null : $digits,
        ];
    }
}
