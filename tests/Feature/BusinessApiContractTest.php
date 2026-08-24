<?php

namespace Tests\Feature;

use App\Support\Generated\BusinessApiOperations;
use Tests\TestCase;

class BusinessApiContractTest extends TestCase
{
    public function test_generated_allowlist_matches_the_released_business_contracts(): void
    {
        $this->artisan('business:sync-contracts', [
            'business_source' => base_path('contracts/business-v1.json'),
            'clinical_source' => base_path('contracts/clinical-v1.json'),
            '--check' => true,
        ])->assertSuccessful();
    }

    public function test_only_contract_declared_routes_are_allowed(): void
    {
        self::assertTrue(BusinessApiOperations::allows('GET', '/v1/business/organizations'));
        self::assertTrue(BusinessApiOperations::allows('PATCH', '/v1/business/offerings/019f5a00-0000-7000-8000-000000000002'));
        self::assertTrue(BusinessApiOperations::allows('GET', '/v1/business/providers?page=2&per_page=25'));
        self::assertTrue(BusinessApiOperations::allows('POST', '/v1/business/clinical/provider-grants/grant-1/care-submissions'));

        self::assertFalse(BusinessApiOperations::allows('DELETE', '/v1/business/organizations'));
        self::assertFalse(BusinessApiOperations::allows('GET', '/v1/business/providers/provider-1/secrets'));
        self::assertFalse(BusinessApiOperations::allows('GET', '/v1/admin/users'));
    }
}
