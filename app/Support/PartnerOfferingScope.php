<?php

declare(strict_types=1);

namespace App\Support;

final class PartnerOfferingScope
{
    /** @param array<string, mixed> $offering */
    public static function label(array $offering): string
    {
        $provider = data_get($offering, 'provider.name');
        $place = data_get($offering, 'place.name');
        if (! is_string($place) || $place === '') {
            $place = array_key_exists('place_id', $offering) && $offering['place_id'] === null ? 'All locations' : 'Location unavailable';
        }

        return is_string($provider) && $provider !== '' && $provider !== $place ? $provider.' · '.$place : $place;
    }
}
