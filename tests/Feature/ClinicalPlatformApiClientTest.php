<?php

namespace Tests\Feature;

use App\Exceptions\PlatformApiException;
use App\Services\PlatformApiClient;
use App\Support\ClinicalPortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ClinicalPlatformApiClientTest extends TestCase
{
    private const GRANT_ID = '019fe05f-3d0e-7079-86e3-e8ab5aa380b1';

    private const SUBMISSION_ID = '019fd545-0f9d-71f4-9767-99cb7f143b2c';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.url' => 'https://business.zigpaw.test',
            'platform.api_url' => 'https://api.zigpaw.test',
            'platform.auth_url' => 'https://login.zigpaw.test',
            'platform.oauth_redirect_uri' => 'https://business.zigpaw.test/auth/callback',
            'platform.session_endpoint' => '/v1/business/session',
            'platform_clinical.api_url' => 'https://api.zigpaw.test',
            'platform_clinical.auth_url' => 'https://login.zigpaw.test',
            'platform_clinical.oauth_redirect_uri' => 'https://business.zigpaw.test/clinical/auth/callback',
            'platform_clinical.session_endpoint' => '/v1/business/clinical/session',
        ]);
    }

    public function test_it_sends_the_clinical_bearer_token_and_organization_context(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/me' => Http::response([
                'data' => ['id' => 'clinician-1'],
            ]),
        ]);

        $identity = $this->api()->clinicalIdentity($this->tokens(), 'organization-1');

        $this->assertSame('clinician-1', $identity['id']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer clinical-access-token')
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1')
            && $request->hasHeader('X-Request-ID'));
    }

    public function test_it_calls_only_the_named_clinical_contract_and_parses_resource_envelopes(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/organizations' => Http::response(['data' => [['id' => 'org-1']]]),
            'https://api.zigpaw.test/v1/business/clinical/dashboard' => Http::response(['data' => ['grants' => ['active' => 1]]]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/care-context' => Http::response(['data' => ['pet' => ['id' => 'pet-1']]]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media' => Http::response(['data' => [['id' => 42]]]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID => Http::response(['data' => ['id' => self::GRANT_ID]]),
            'https://api.zigpaw.test/v1/business/clinical/provider-grants?*' => Http::response($this->page([['id' => self::GRANT_ID]])),
            'https://api.zigpaw.test/v1/business/clinical/submissions/'.self::SUBMISSION_ID => Http::response(['data' => ['id' => self::SUBMISSION_ID]]),
            'https://api.zigpaw.test/v1/business/clinical/submissions?*' => Http::response($this->page([['id' => self::SUBMISSION_ID]])),
        ]);

        $api = $this->api();
        $tokens = $this->tokens();

        $this->assertSame('org-1', $api->clinicalOrganizations($tokens)[0]['id']);
        $this->assertSame(1, $api->clinicalDashboard($tokens, 'org-1')['grants']['active']);
        $this->assertSame(
            self::GRANT_ID,
            $api->clinicalProviderGrants($tokens, 'org-1', 1, 25, 'active', '  Patchy  ')['data'][0]['id'],
        );
        $this->assertSame(self::GRANT_ID, $api->clinicalProviderGrant($tokens, 'org-1', self::GRANT_ID)['id']);
        $this->assertSame('pet-1', $api->clinicalCareContext($tokens, 'org-1', self::GRANT_ID)['pet']['id']);
        $this->assertSame(42, $api->clinicalProviderMedia($tokens, 'org-1', self::GRANT_ID)[0]['id']);
        $this->assertSame(
            self::SUBMISSION_ID,
            $api->clinicalSubmissions($tokens, 'org-1', 1, 10, 'pending')['data'][0]['id'],
        );
        $this->assertSame(
            self::SUBMISSION_ID,
            $api->clinicalSubmission($tokens, 'org-1', self::SUBMISSION_ID)['id'],
        );

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/clinical/provider-grants?')) {
                return false;
            }

            return $request['status'] === 'active'
                && $request['search'] === 'Patchy'
                && (int) $request['per_page'] === 25
                && (int) $request['page'] === 1;
        });
    }

    public function test_it_sends_care_submissions_with_the_caller_idempotency_key(): void
    {
        $idempotencyKey = 'care-submission:'.self::SUBMISSION_ID;
        $payload = [
            'vet_name' => 'Dr Rivera',
            'clinic_name' => 'Riverbank Veterinary Clinic',
            'clinic_email' => 'clinic@example.test',
            'visit_date' => '2026-08-16',
            'visit_type' => 'checkup',
        ];
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/care-submissions' => Http::response([
                'data' => ['id' => self::SUBMISSION_ID, 'status' => 'pending'],
            ], 201),
        ]);

        $submission = $this->api()->createClinicalCareSubmission(
            $this->tokens(),
            'organization-1',
            self::GRANT_ID,
            $payload,
            $idempotencyKey,
        );

        $this->assertSame('pending', $submission['status']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->hasHeader('Idempotency-Key', $idempotencyKey)
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1')
            && $request->data() === $payload);
    }

    public function test_it_returns_media_as_an_untouched_response_for_the_secure_proxy(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/42' => Http::response(
                'binary-image-body',
                200,
                ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $response = $this->api()->clinicalProviderMediaDownload(
            $this->tokens(),
            'organization-1',
            self::GRANT_ID,
            42,
        );

        $this->assertSame('binary-image-body', $response->body());
        $this->assertSame('image/jpeg', $response->header('Content-Type'));
    }

    public function test_it_rejects_invalid_clinical_identifiers_before_sending(): void
    {
        Http::fake();

        try {
            $this->api()->clinicalProviderGrant($this->tokens(), 'org-1', 'not-a-uuid');
            self::fail('A non-UUID provider grant identifier should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid provider grant identifier.', $exception->getMessage());
        } catch (PlatformApiException $exception) {
            self::fail('The invalid provider grant reached the transport: '.$exception->getMessage());
        }

        try {
            $this->api()->clinicalProviderMediaDownload($this->tokens(), 'org-1', self::GRANT_ID, 0);
            self::fail('A non-positive media identifier should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid media identifier.', $exception->getMessage());
        } catch (PlatformApiException $exception) {
            self::fail('The invalid media identifier reached the transport: '.$exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_requires_a_safe_caller_supplied_idempotency_key(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid idempotency key.');

        $this->api()->createClinicalCareSubmission(
            $this->tokens(),
            'org-1',
            self::GRANT_ID,
            ['visit_type' => 'checkup'],
            "bad\r\nkey",
        );
    }

    public function test_it_carries_platform_validation_errors_and_request_correlation(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/dashboard' => Http::response([
                'message' => 'The visit details are invalid.',
                'errors' => [
                    'visit_date' => ['The visit date must be before or equal to today.'],
                    'clinic_email' => 'The clinic email field is required.',
                    0 => ['ignored'],
                ],
            ], 422, ['X-Request-ID' => 'request-12345678']),
        ]);

        try {
            $this->api()->clinicalDashboard($this->tokens(), 'org-1');
            self::fail('The platform validation response should throw.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertSame('The visit details are invalid.', $exception->getMessage());
            $this->assertSame([
                'visit_date' => ['The visit date must be before or equal to today.'],
                'clinic_email' => ['The clinic email field is required.'],
            ], $exception->errors);
            $this->assertSame('request-12345678', data_get($exception, 'requestId'));
        }
    }

    public function test_it_rejects_malformed_success_responses(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/me' => Http::response('not-json', 200, [
                'Content-Type' => 'text/plain',
                'X-Request-ID' => 'request-87654321',
            ]),
        ]);

        try {
            $this->api()->clinicalIdentity($this->tokens(), 'org-1');
            self::fail('A malformed platform response should throw.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(502, $exception->status);
            $this->assertSame('Zigpaw returned an unexpected response.', $exception->getMessage());
            $this->assertSame('request-87654321', data_get($exception, 'requestId'));
        }
    }

    public function test_it_bounds_malformed_server_error_responses(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/me' => Http::response(
                '<html>internal upstream details</html>',
                500,
                ['Content-Type' => 'text/html', 'X-Request-ID' => 'request-50000000'],
            ),
        ]);

        try {
            $this->api()->clinicalIdentity($this->tokens(), 'org-1');
            self::fail('A malformed server error should throw.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(500, $exception->status);
            $this->assertSame('Zigpaw is temporarily unavailable. Please try again shortly.', $exception->getMessage());
            $this->assertSame([], $exception->errors);
            $this->assertSame('request-50000000', data_get($exception, 'requestId'));
        }
    }

    public function test_it_converts_connection_failures_into_a_graceful_platform_exception(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/me' => Http::failedConnection(),
        ]);

        try {
            $this->api()->clinicalIdentity($this->tokens(), 'org-1');
            self::fail('A connection failure should throw.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertSame('Zigpaw is unavailable right now. Please try again shortly.', $exception->getMessage());
        }
    }

    public function test_it_never_sends_clinical_credentials_to_a_noncanonical_platform_origin(): void
    {
        config()->set('platform_clinical.api_url', 'https://api.zigpaw.test.attacker.example');
        Http::fake([
            'https://api.zigpaw.test.attacker.example/*' => Http::response(['data' => ['id' => 'attacker']]),
        ]);

        try {
            $this->api()->clinicalIdentity($this->tokens(), 'org-1');
            self::fail('Unsafe platform configuration should be rejected.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertSame('Zigpaw clinical is temporarily unavailable.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    private function api(): PlatformApiClient
    {
        return app(PlatformApiClient::class);
    }

    private function tokens(): ClinicalPortalAccessTokenStore
    {
        $tokens = app(ClinicalPortalAccessTokenStore::class);
        if (! $tokens->accessToken()) {
            $tokens->put([
                'access_token' => 'clinical-access-token',
                'refresh_token' => 'clinical-refresh-token',
                'expires_in' => 900,
            ]);
        }

        return $tokens;
    }

    /** @param list<array<string, mixed>> $data @return array<string, mixed> */
    private function page(array $data): array
    {
        return [
            'data' => $data,
            'links' => ['first' => null, 'last' => null, 'prev' => null, 'next' => null],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => count($data)],
        ];
    }
}
