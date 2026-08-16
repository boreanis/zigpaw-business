<?php

namespace App\Support;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final class RequestCorrelation
{
    public static function id(?string $candidate = null): string
    {
        $requestId = self::valid($candidate)
            ?? self::valid(Context::get('request_id'))
            ?? (string) Str::uuid();

        Context::add('request_id', $requestId);

        return $requestId;
    }

    public static function valid(mixed $requestId): ?string
    {
        if (! is_string($requestId) || $requestId === '') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/D', $requestId) === 1
            ? $requestId
            : null;
    }
}
