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
    public function test_it_sends_the_bearer_token_to_the_clinical_api(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/vets/me' => Http::response(['data' => ['id' => 'clinician-1']], 200),
        ]);

        $identity = app(PlatformApiClient::class)->identity('access-token', 'organization-1');

        $this->assertSame('clinician-1', $identity['id']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer access-token')
            && $request->hasHeader('X-Zigpaw-Organization-ID', 'organization-1')
            && $request->hasHeader('X-Request-ID'));
    }

    public function test_it_calls_only_the_named_clinical_contract_and_parses_resource_envelopes(): void
    {
        $grantId = '019fe05f-3d0e-7079-86e3-e8ab5aa380b1';
        $submissionId = '019fd545-0f9d-71f4-9767-99cb7f143b2c';

        Http::fake([
            'https://api.zigpaw.test/v1/vets/organizations' => Http::response(['data' => [['id' => 'org-1']]]),
            'https://api.zigpaw.test/v1/vets/dashboard' => Http::response(['data' => ['grants' => ['active' => 1]]]),
            "https://api.zigpaw.test/v1/vets/provider-grants/{$grantId}" => Http::response(['data' => ['id' => $grantId]]),
            "https://api.zigpaw.test/v1/vets/provider-grants/{$grantId}/care-context" => Http::response(['data' => ['pet' => ['id' => 'pet-1']]]),
            "https://api.zigpaw.test/v1/vets/provider-grants/{$grantId}/media" => Http::response(['data' => [['id' => 42]]]),
            'https://api.zigpaw.test/v1/vets/provider-grants*' => Http::response([
                'data' => [['id' => $grantId]],
                'links' => ['next' => null],
                'meta' => ['current_page' => 1],
            ]),
            "https://api.zigpaw.test/v1/vets/submissions/{$submissionId}" => Http::response(['data' => ['id' => $submissionId]]),
            'https://api.zigpaw.test/v1/vets/submissions*' => Http::response([
                'data' => [['id' => $submissionId]],
                'links' => ['next' => null],
                'meta' => ['current_page' => 1],
            ]),
        ]);

        $client = app(PlatformApiClient::class);

        $this->assertSame('org-1', $client->organizations('token')[0]['id']);
        $this->assertSame(1, $client->dashboard('token', 'org-1')['grants']['active']);
        $this->assertSame(
            $grantId,
            $client->providerGrants('token', 'org-1', [
                'status' => 'active',
                'search' => '  Patchy  ',
                'per_page' => 25,
                'page' => 1,
            ])['data'][0]['id'],
        );
        $this->assertSame($grantId, $client->providerGrant('token', 'org-1', $grantId)['id']);
        $this->assertSame('pet-1', $client->careContext('token', 'org-1', $grantId)['pet']['id']);
        $this->assertSame(42, $client->providerMedia('token', 'org-1', $grantId)[0]['id']);
        $this->assertSame(
            $submissionId,
            $client->submissions('token', 'org-1', ['status' => 'pending', 'per_page' => 10, 'page' => 1])['data'][0]['id'],
        );
        $this->assertSame($submissionId, $client->submission('token', 'org-1', $submissionId)['id']);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/provider-grants?')) {
                return false;
            }

            return $request['status'] === 'active'
                && $request['search'] === 'Patchy'
                && $request['per_page'] === 25
                && $request['page'] === 1;
        });
    }

    public function test_it_sends_care_submissions_with_the_caller_idempotency_key(): void
    {
        $grantId = '019fe05f-3d0e-7079-86e3-e8ab5aa380b1';
        $idempotencyKey = 'care-submission:019fd545-0f9d-71f4-9767-99cb7f143b2c';
        $payload = [
            'vet_name' => 'Dr Rivera',
            'clinic_name' => 'Riverbank Veterinary Clinic',
            'clinic_email' => 'clinic@example.test',
            'visit_date' => '2026-08-16',
            'visit_type' => 'checkup',
        ];

        Http::fake([
            "https://api.zigpaw.test/v1/vets/provider-grants/{$grantId}/care-submissions" => Http::response([
                'data' => ['id' => '019fd545-0f9d-71f4-9767-99cb7f143b2c', 'status' => 'pending'],
            ], 201),
        ]);

        $submission = app(PlatformApiClient::class)->createCareSubmission(
            'access-token',
            'organization-1',
            $grantId,
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
        $grantId = '019fe05f-3d0e-7079-86e3-e8ab5aa380b1';

        Http::fake([
            "https://api.zigpaw.test/v1/vets/provider-grants/{$grantId}/media/42" => Http::response(
                'binary-image-body',
                200,
                ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $response = app(PlatformApiClient::class)->providerMediaDownload(
            'access-token',
            'organization-1',
            $grantId,
            42,
        );

        $this->assertSame('binary-image-body', $response->body());
        $this->assertSame('image/jpeg', $response->header('Content-Type'));
    }

    public function test_it_rejects_traversal_identifiers_and_unsupported_query_parameters_before_sending(): void
    {
        Http::fake();
        $client = app(PlatformApiClient::class);

        try {
            $client->providerGrant('token', 'org-1', '../../orders');
            $this->fail('A traversal identifier should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid provider grant identifier.', $exception->getMessage());
        }

        try {
            $client->providerGrants('token', 'org-1', ['redirect' => 'https://example.test']);
            $this->fail('An unsupported query parameter should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Unsupported platform API query parameter.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_requires_a_safe_caller_supplied_idempotency_key(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid idempotency key.');

        app(PlatformApiClient::class)->createCareSubmission(
            'token',
            'org-1',
            '019fe05f-3d0e-7079-86e3-e8ab5aa380b1',
            ['visit_type' => 'checkup'],
            "bad\r\nkey",
        );
    }

    public function test_it_carries_platform_validation_errors_and_request_correlation(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/vets/dashboard' => Http::response([
                'message' => 'The visit details are invalid.',
                'errors' => [
                    'visit_date' => ['The visit date must be before or equal to today.'],
                    'clinic_email' => 'The clinic email field is required.',
                    0 => ['ignored'],
                ],
            ], 422, ['X-Request-ID' => 'request-12345678']),
        ]);

        try {
            app(PlatformApiClient::class)->dashboard('token', 'org-1');
            $this->fail('The platform validation response should throw.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(422, $exception->status);
            $this->assertSame('The visit details are invalid.', $exception->getMessage());
            $this->assertSame([
                'visit_date' => ['The visit date must be before or equal to today.'],
                'clinic_email' => ['The clinic email field is required.'],
            ], $exception->errors);
            $this->assertSame('request-12345678', $exception->requestId);
        }
    }

    public function test_it_rejects_malformed_success_responses(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/vets/me' => Http::response('not-json', 200, [
                'Content-Type' => 'text/plain',
                'X-Request-ID' => 'request-87654321',
            ]),
        ]);

        try {
            app(PlatformApiClient::class)->identity('token', 'org-1');
            $this->fail('A malformed platform response should throw.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(502, $exception->status);
            $this->assertSame('Zigpaw returned an unexpected response.', $exception->getMessage());
            $this->assertSame('request-87654321', $exception->requestId);
        }
    }

    public function test_it_converts_connection_failures_into_a_graceful_platform_exception(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/vets/me' => Http::failedConnection(),
        ]);

        try {
            app(PlatformApiClient::class)->identity('token', 'org-1');
            $this->fail('A connection failure should throw.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertSame('Zigpaw is unavailable right now. Please try again shortly.', $exception->getMessage());
        }
    }

    public function test_it_never_sends_credentials_to_a_noncanonical_platform_origin(): void
    {
        config()->set('platform.api_url', 'https://api.zigpaw.test.attacker.example');
        Http::preventStrayRequests();

        try {
            app(PlatformApiClient::class)->identity('secret-access-token', 'org-1');
            $this->fail('Unsafe platform configuration should be rejected.');
        } catch (PlatformApiException $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertSame('Zigpaw clinical is temporarily unavailable.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
