<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Generated\BusinessApiOperations;
use Tests\TestCase;

final class BusinessCommerceBoundaryTest extends TestCase
{
    public function test_business_can_only_read_platform_recorded_partner_program_activity(): void
    {
        self::assertTrue(BusinessApiOperations::allows('GET', '/v1/business/programs'));
        self::assertTrue(BusinessApiOperations::allows('GET', '/v1/business/financials'));
        self::assertTrue(BusinessApiOperations::allows('GET', '/v1/business/financials/commissions'));
        self::assertTrue(BusinessApiOperations::allows('GET', '/v1/business/financials/agreements'));
    }

    public function test_business_cannot_execute_consumer_commerce_or_provider_payment_operations(): void
    {
        foreach ([
            '/v1/checkout',
            '/v1/orders',
            '/v1/inventory',
            '/v1/stripe/checkout-sessions',
            '/v1/business/payments',
            '/v1/business/tax',
        ] as $path) {
            self::assertFalse(
                BusinessApiOperations::allows('GET', $path),
                "Unexpected Business commerce route: {$path}",
            );
            self::assertFalse(
                BusinessApiOperations::allows('POST', $path),
                "Unexpected Business commerce route: {$path}",
            );
        }
    }
}
