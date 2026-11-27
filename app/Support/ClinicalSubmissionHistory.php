<?php

declare(strict_types=1);

namespace App\Support;

final class ClinicalSubmissionHistory
{
    public const STATUSES = [
        'all' => 'All statuses',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'partially_approved' => 'Partly approved',
        'rejected' => 'Not added',
    ];

    /** @return array{status?: string, page?: int} */
    public static function query(mixed $status, mixed $page): array
    {
        $query = [];
        if (is_string($status) && $status !== 'all' && isset(self::STATUSES[$status])) {
            $query['status'] = $status;
        }
        $page = filter_var($page, FILTER_VALIDATE_INT);
        if ($page !== false && $page > 1) {
            $query['page'] = min(100_000, $page);
        }

        return $query;
    }
}
