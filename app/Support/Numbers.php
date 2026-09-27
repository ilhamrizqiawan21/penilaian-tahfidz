<?php

namespace App\Support;

class Numbers
{
    public static function trim(null|string|int|float $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = (string) $value;
        if (! str_contains($value, '.')) {
            return $value;
        }
        $value = rtrim(rtrim($value, '0'), '.');

        return $value === '' ? '0' : $value;
    }
}
