<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function minor(string|int|float $value): int
    {
        $value = (string) $value;
        if (! preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,2}))?$/', $value, $parts)) {
            throw ValidationException::withMessages(['amount' => 'Use a non-negative amount with at most two decimal places.']);
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
