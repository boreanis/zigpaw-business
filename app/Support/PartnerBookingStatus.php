<?php

declare(strict_types=1);

namespace App\Support;

final class PartnerBookingStatus
{
    public const QUICK_FILTERS = ['all' => 'All requests', 'requested' => 'Needs response', 'accepted' => 'Accepted'];

    public const OTHER_FILTERS = [
        'proposed' => 'Proposed', 'completed' => 'Completed', 'declined' => 'Declined',
        'cancelled' => 'Cancelled', 'expired' => 'Expired', 'draft' => 'Draft',
    ];

    public static function filter(mixed $status): ?string
    {
        if (! is_string($status) || $status === 'all') {
            return null;
        }

        return isset((self::QUICK_FILTERS + self::OTHER_FILTERS)[$status]) ? $status : null;
    }
}
