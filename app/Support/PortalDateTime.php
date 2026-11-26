<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

final class PortalDateTime
{
    public static function parse(mixed $value, string $timezone = 'UTC', string $locale = 'en'): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->timezone($timezone)->locale($locale);
        } catch (Throwable) {
            return null;
        }
    }
}
