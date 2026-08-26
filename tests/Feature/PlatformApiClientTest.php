<?php

namespace Tests\Feature;

use App\Exceptions\PlatformApiException;
use App\Services\PlatformApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class PlatformApiClientTest extends TestCase
{
    public function test_it_sends_the_active_organization_header_only_when_selected(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/me' => Http::response(['data' => ['organization' => ['id' => 'organization-1']]], 200),
        ]);

        $identity = app(PlatformApiClient::class)->identity('access-token', 'organization-1');

        $this->assertSame('organization-1', $identity['organization']['id']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer access-token')
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1'));
    }

    public function test_mutations_use_the_business_contract_and_an_idempotency_key(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/offerings' => Http::response([
                'data' => ['id' => 'offering-1', 'name' => 'Wellness consultation'],
            ], 201),
        ]);

        $offering = app(PlatformApiClient::class)->createOffering('access-token', 'organization-1', [
            'provider_link_id' => 'link-1',
            'name' => 'Wellness consultation',
        ]);

        $this->assertSame('offering-1', $offering['id']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.zigpaw.test/v1/business/offerings'
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1')
            && $request->hasHeader('Idempotency-Key'));
    }

    public function test_workspace_management_mutations_use_the_canonical_business_routes(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/me' => Http::response(['data' => ['organization' => ['id' => 'organization-1']]]),
            'https://api.zigpaw.test/v1/business/providers/019f5a00-0000-7000-8000-000000000001' => Http::response(['data' => ['id' => '019f5a00-0000-7000-8000-000000000001']]),
            'https://api.zigpaw.test/v1/business/booking-profiles' => Http::response(['data' => ['id' => 'profile-1']]),
            'https://api.zigpaw.test/v1/business/offerings/019f5a00-0000-7000-8000-000000000002' => Http::sequence()
                ->push(['data' => ['id' => '019f5a00-0000-7000-8000-000000000002', 'status' => 'active']])
                ->push([], 204),
            'https://api.zigpaw.test/v1/business/team/memberships/019f5a00-0000-7000-8000-000000000003' => Http::sequence()
                ->push(['data' => ['id' => '019f5a00-0000-7000-8000-000000000003', 'role' => 'manager']])
                ->push(['data' => ['id' => '019f5a00-0000-7000-8000-000000000003', 'status' => 'revoked']]),
            'https://api.zigpaw.test/v1/business/team/invitations/019f5a00-0000-7000-8000-000000000003/resend' => Http::response(['data' => ['id' => '019f5a00-0000-7000-8000-000000000003']]),
        ]);

        $api = app(PlatformApiClient::class);
        $api->updateBusinessProfile('access-token', 'organization-1', ['name' => 'Zigpaw Vet']);
        $api->updateManagedProvider('access-token', 'organization-1', '019f5a00-0000-7000-8000-000000000001', ['provider' => ['name' => 'Zigpaw Vet']]);
        $api->updateBookingProfile('access-token', 'organization-1', ['provider_link_id' => 'link-1', 'timezone' => 'Australia/Brisbane']);
        $api->updateOffering('access-token', 'organization-1', '019f5a00-0000-7000-8000-000000000002', ['status' => 'active']);
        $api->deleteOffering('access-token', 'organization-1', '019f5a00-0000-7000-8000-000000000002');
        $api->updateTeamMember('access-token', 'organization-1', '019f5a00-0000-7000-8000-000000000003', ['role' => 'manager']);
        $api->resendTeamInvitation('access-token', 'organization-1', '019f5a00-0000-7000-8000-000000000003');
        $api->revokeTeamMember('access-token', 'organization-1', '019f5a00-0000-7000-8000-000000000003');

        foreach ([
            ['PATCH', '/v1/business/me'],
            ['PATCH', '/v1/business/providers/019f5a00-0000-7000-8000-000000000001'],
            ['PUT', '/v1/business/booking-profiles'],
            ['PATCH', '/v1/business/offerings/019f5a00-0000-7000-8000-000000000002'],
            ['DELETE', '/v1/business/offerings/019f5a00-0000-7000-8000-000000000002'],
            ['PATCH', '/v1/business/team/memberships/019f5a00-0000-7000-8000-000000000003'],
            ['POST', '/v1/business/team/invitations/019f5a00-0000-7000-8000-000000000003/resend'],
            ['DELETE', '/v1/business/team/memberships/019f5a00-0000-7000-8000-000000000003'],
        ] as [$method, $path]) {
            Http::assertSent(fn (Request $request): bool => $request->method() === $method
                && $request->url() === 'https://api.zigpaw.test'.$path
                && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1')
                && $request->hasHeader('Idempotency-Key'));
        }
    }

    public function test_paginated_endpoints_preserve_api_owned_page_metadata(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/providers*' => Http::response([
                'data' => [['id' => 'provider-link-1']],
                'links' => [
                    'first' => 'https://api.zigpaw.test/v1/business/providers?page=1',
                    'last' => 'https://api.zigpaw.test/v1/business/providers?page=4',
                    'prev' => 'https://api.zigpaw.test/v1/business/providers?page=1',
                    'next' => 'https://api.zigpaw.test/v1/business/providers?page=3',
                ],
                'meta' => ['current_page' => 2, 'last_page' => 4, 'per_page' => 25, 'total' => 88],
            ]),
        ]);

        $page = app(PlatformApiClient::class)->providers('access-token', 'organization-1', 2);

        $this->assertSame('provider-link-1', $page['data'][0]['id']);
        $this->assertSame(2, $page['meta']['current_page']);
        $this->assertSame(88, $page['meta']['total']);
        $this->assertNotNull($page['links']['next']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'page=2')
            && str_contains($request->url(), 'per_page=25'));
    }

    public function test_provider_claim_discovery_and_submission_use_the_scoped_business_contract(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/provider-claims/discovery*' => Http::response([
                'data' => [['service_provider_id' => 'provider-1', 'place_id' => 'place-1']],
                'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 1],
            ]),
            'https://api.zigpaw.test/v1/business/provider-claims' => Http::response([
                'data' => ['id' => 'claim-1', 'status' => 'pending'],
            ], 201),
        ]);

        $api = app(PlatformApiClient::class);
        $results = $api->claimableProviders('access-token', 'organization-1', 'Laidley Veterinary', 1, 10);
        $claim = $api->submitProviderClaim('access-token', 'organization-1', [
            'service_provider_id' => 'provider-1',
            'place_id' => 'place-1',
            'requested_authority_type' => 'manager',
            'verification_method' => 'domain_email',
            'claimant_email' => 'manager@example.test',
        ]);

        $this->assertSame('provider-1', $results['data'][0]['service_provider_id']);
        $this->assertSame('claim-1', $claim['id']);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://api.zigpaw.test/v1/business/provider-claims/discovery?')
                && ($query['q'] ?? null) === 'Laidley Veterinary'
                && ($query['per_page'] ?? null) === '10'
                && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1');
        });
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.zigpaw.test/v1/business/provider-claims'
            && $request['service_provider_id'] === 'provider-1'
            && $request['place_id'] === 'place-1'
            && $request->hasHeader('Idempotency-Key'));
    }

    public function test_problem_details_are_kept_for_feature_rollout_feedback(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/provider-claims*' => Http::response([
                'type' => 'https://api.zigpaw.app/problems/feature-unavailable',
                'title' => 'Feature unavailable',
                'status' => 404,
                'detail' => 'This business feature is not available for this account.',
                'code' => 'feature_unavailable',
            ], 404),
        ]);

        try {
            app(PlatformApiClient::class)->providerClaims('access-token', 'organization-1');
            self::fail('The feature-off response should be surfaced to the workspace.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(404, $exception->status);
            $this->assertSame('This business feature is not available for this account.', $exception->getMessage());
        }
    }

    public function test_livewire_resource_identifiers_cannot_escape_api_path_segments(): void
    {
        Http::fake();

        $api = app(PlatformApiClient::class);

        $operations = [
            static fn (): array => $api->bookingPetContext('access-token', 'organization-1', '../other'),
            static fn (): array => $api->respondToBooking('access-token', 'organization-1', 'booking/other', ['response' => 'accept']),
            static fn (): array => $api->updateManagedProvider('access-token', 'organization-1', 'provider?redirect=https://attacker.example', []),
            static fn (): array => $api->updateOffering('access-token', 'organization-1', 'offering#fragment', []),
            static fn (): array => $api->updateTeamMember('access-token', 'organization-1', "member\nX-Injected: yes", []),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                self::fail('An unsafe resource identifier was accepted.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        Http::assertNothingSent();
    }

    public function test_organization_context_is_validated_before_being_sent_as_a_header(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        app(PlatformApiClient::class)->identity('access-token', 'organization/other');

        Http::assertNothingSent();
    }
}
