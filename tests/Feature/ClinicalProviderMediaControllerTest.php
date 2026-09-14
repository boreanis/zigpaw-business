<?php

namespace Tests\Feature;

use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\PortalAccessTokenStore;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClinicalProviderMediaControllerTest extends TestCase
{
    private const GRANT_ID = '019fe05f-3d0e-7079-86e3-e8ab5aa380b1';

    private const MEDIA_ID = '019fe05f-3d0e-7079-86e3-e8ab5aa380b2';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.url' => 'https://business.zigpaw.test',
            'platform_clinical.api_url' => 'https://api.zigpaw.test',
            'platform_clinical.auth_url' => 'https://auth.zigpaw.test',
            'platform_clinical.oauth_redirect_uri' => 'https://business.zigpaw.test/clinical/auth/callback',
            'platform_clinical.session_endpoint' => '/v1/business/clinical/session',
        ]);
    }

    public function test_it_streams_expected_binary_media_without_leaking_upstream_headers(): void
    {
        $body = str_repeat('synthetic-media-chunk', 20000);
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                $body,
                200,
                [
                    'Content-Type' => 'image/jpeg; charset=binary',
                    'Content-Length' => (string) strlen($body),
                    'Content-Disposition' => 'inline; filename="private-original.jpg"',
                    'ETag' => 'private-etag',
                ],
            ),
        ]);
        $this->signInToClinicalWorkspace();

        $response = $this->get(route('clinical.patients.media.show', [
            'grantId' => self::GRANT_ID,
            'mediaId' => self::MEDIA_ID,
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('Content-Length', (string) strlen($body))
            ->assertHeader('Content-Disposition', 'attachment; filename="clinical-media-'.self::MEDIA_ID.'"')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('ETag')
            ->assertHeaderMissing('Last-Modified');
        self::assertSame($body, $response->streamedContent());
    }

    #[DataProvider('deniedMediaResponses')]
    public function test_it_normalizes_upstream_errors_without_returning_raw_private_body(int $status, string $message): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                '<html>upstream private details</html>',
                $status,
                ['Content-Type' => 'text/html'],
            ),
        ]);
        $this->signInToClinicalWorkspace();

        $response = $this->get(route('clinical.patients.media.show', [
            'grantId' => self::GRANT_ID,
            'mediaId' => self::MEDIA_ID,
        ]));

        $response->assertRedirect(route('clinical.patients.show', ['grantId' => self::GRANT_ID]))
            ->assertSessionHas('error', $message);
        self::assertStringNotContainsString('upstream private details', (string) $response->getSession()->get('error'));
    }

    /** @return array<string, array{int, string}> */
    public static function deniedMediaResponses(): array
    {
        return [
            'unauthorized' => [401, 'Your secure clinical session has ended. Please sign in again.'],
            'forbidden' => [403, 'This family has not shared that file with this clinical team.'],
            'not found' => [404, 'That shared file is no longer available.'],
            'validation' => [422, 'The shared file request was not accepted.'],
            'server error' => [500, 'The shared file is temporarily unavailable.'],
        ];
    }

    public function test_it_rejects_a_non_media_success_without_returning_the_upstream_body(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                '{"data":"not-a-media-response"}',
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);
        $this->signInToClinicalWorkspace();

        $this->get(route('clinical.patients.media.show', [
            'grantId' => self::GRANT_ID,
            'mediaId' => self::MEDIA_ID,
        ]))->assertRedirect(route('clinical.patients.show', ['grantId' => self::GRANT_ID]))
            ->assertSessionHas('error', 'The shared file is temporarily unavailable.');
    }

    public function test_it_rejects_an_oversized_binary_before_opening_a_downstream_response(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                'synthetic oversized body',
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => (string) (10 * 1024 * 1024 + 1),
                ],
            ),
        ]);
        $this->signInToClinicalWorkspace();

        $this->get(route('clinical.patients.media.show', [
            'grantId' => self::GRANT_ID,
            'mediaId' => self::MEDIA_ID,
        ]))->assertRedirect(route('clinical.patients.show', ['grantId' => self::GRANT_ID]))
            ->assertSessionHas('error', 'The shared file is temporarily unavailable.');
    }

    public function test_it_caps_a_binary_stream_when_content_length_is_missing(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                str_repeat('x', (10 * 1024 * 1024) + 1),
                200,
                ['Content-Type' => 'application/pdf'],
            ),
        ]);
        $this->signInToClinicalWorkspace();
        $response = $this->get(route('clinical.patients.media.show', [
            'grantId' => self::GRANT_ID,
            'mediaId' => self::MEDIA_ID,
        ]));

        $response->assertRedirect(route('clinical.patients.show', ['grantId' => self::GRANT_ID]))
            ->assertSessionHas('error', 'The shared file is temporarily unavailable.');
        self::assertStringNotContainsString('xxxxx', $response->getContent());
    }

    public function test_it_fails_closed_for_truncated_or_empty_binary_streams(): void
    {
        foreach ([
            'truncated' => ['body' => 'short', 'headers' => ['Content-Type' => 'image/jpeg', 'Content-Length' => '10']],
            'empty' => ['body' => '', 'headers' => ['Content-Type' => 'image/jpeg']],
        ] as $case) {
            Http::fake([
                'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                    $case['body'],
                    200,
                    $case['headers'],
                ),
            ]);
            $this->signInToClinicalWorkspace();
            $response = $this->get(route('clinical.patients.media.show', [
                'grantId' => self::GRANT_ID,
                'mediaId' => self::MEDIA_ID,
            ]));

            $response->assertRedirect(route('clinical.patients.show', ['grantId' => self::GRANT_ID]))
                ->assertSessionHas('error', 'The shared file is temporarily unavailable.');
            self::assertStringNotContainsString('short', $response->getContent());
        }
    }

    public function test_it_rejects_a_malformed_media_route_before_upstream_dispatch(): void
    {
        $this->signInToClinicalWorkspace();
        Http::fake();

        $this->get('/clinical/patients/'.self::GRANT_ID.'/media/not-a-uuid')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_clinical_media_revocation_does_not_clear_the_management_session(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/clinical/provider-grants/'.self::GRANT_ID.'/media/'.self::MEDIA_ID => Http::response(
                '{"message":"private upstream details"}',
                401,
                ['Content-Type' => 'application/json'],
            ),
        ]);
        $this->signInToClinicalWorkspace();
        $businessTokens = app(PortalAccessTokenStore::class);
        $businessTokens->put([
            'access_token' => 'business-access-token',
            'refresh_token' => 'business-refresh-token',
            'expires_in' => 900,
        ]);

        $this->get(route('clinical.patients.media.show', [
            'grantId' => self::GRANT_ID,
            'mediaId' => self::MEDIA_ID,
        ]))->assertRedirect();

        self::assertNull(app(ClinicalPortalAccessTokenStore::class)->accessToken());
        self::assertSame('business-access-token', $businessTokens->accessToken());
    }

    private function signInToClinicalWorkspace(): void
    {
        app(ClinicalPortalAccessTokenStore::class)->put([
            'access_token' => 'clinical-access-token',
            'refresh_token' => 'clinical-refresh-token',
            'expires_in' => 900,
        ]);
        session()->put('portal.organization_id', 'organization-1');
    }
}
