<?php

namespace Tests\Feature;

use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PortalAccessTokenStoreContextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.url' => 'https://business.zigpaw.test',
            'platform.api_url' => 'https://api.zigpaw.test',
            'platform.auth_url' => 'https://login.zigpaw.test',
            'platform.session_endpoint' => '/v1/business/session',
            'platform.oauth_client_id' => 'business-client',
            'platform.oauth_client_secret' => str_repeat('b', 40),
            'platform.oauth_scopes' => ['business:read'],
            'platform.oauth_redirect_uri' => 'https://business.zigpaw.test/auth/callback',
            'platform_clinical.api_url' => 'https://api.zigpaw.test',
            'platform_clinical.auth_url' => 'https://login.zigpaw.test',
            'platform_clinical.session_endpoint' => '/v1/business/clinical/session',
            'platform_clinical.oauth_client_id' => 'clinical-client',
            'platform_clinical.oauth_client_secret' => str_repeat('c', 40),
            'platform_clinical.oauth_scopes' => ['clinical:read', 'clinical:submit'],
            'platform_clinical.oauth_redirect_uri' => 'https://business.zigpaw.test/clinical/auth/callback',
        ]);
    }

    /** @return iterable<string, array{class-string<PortalAccessTokenStore>, string, string, string}> */
    public static function portalContexts(): iterable
    {
        yield 'business' => [
            PortalAccessTokenStore::class,
            'platform',
            'platform.oauth.token_handle',
            '/v1/business/session',
        ];
        yield 'clinical' => [
            ClinicalPortalAccessTokenStore::class,
            'platform_clinical',
            'platform.oauth.clinical_token_handle',
            '/v1/business/clinical/session',
        ];
    }

    public function test_business_and_clinical_tokens_use_separate_browser_handles_and_cache_entries(): void
    {
        $business = app(PortalAccessTokenStore::class);
        $clinical = app(ClinicalPortalAccessTokenStore::class);
        $business->put($this->tokenPayload('business'));
        $clinical->put($this->tokenPayload('clinical'));

        $businessHandle = session('platform.oauth.token_handle');
        $clinicalHandle = session('platform.oauth.clinical_token_handle');

        $this->assertIsString($businessHandle);
        $this->assertIsString($clinicalHandle);
        $this->assertNotSame($businessHandle, $clinicalHandle);
        $this->assertTrue(Cache::has($this->cacheKey($businessHandle)));
        $this->assertTrue(Cache::has($this->cacheKey($clinicalHandle)));
        $this->assertSame('business-access-token', $business->accessToken());
        $this->assertSame('clinical-access-token', $clinical->accessToken());
    }

    #[DataProvider('portalContexts')]
    public function test_encrypted_tokens_survive_safe_browser_session_rotation(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        $store = $this->store($storeClass);
        $store->put($this->tokenPayload('active'));
        $handle = session($handleKey);

        session()->migrate(destroy: true);

        $this->assertSame($handle, session($handleKey));
        $this->assertSame('active-access-token', $store->accessToken());
    }

    #[DataProvider('portalContexts')]
    public function test_expired_tokens_refresh_without_exposing_credentials_to_the_browser(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake([
            'https://login.zigpaw.test/oauth/token' => Http::response([
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'rotated-refresh-token',
                'expires_in' => 900,
                'token_type' => 'Bearer',
            ]),
        ]);
        $store = $this->store($storeClass);
        $this->expire($store);

        $this->assertSame('fresh-access-token', $store->accessToken());
        Http::assertSent(fn (Request $request): bool => $request['grant_type'] === 'refresh_token'
            && $request['client_id'] === config($configPrefix.'.oauth_client_id')
            && $request['client_secret'] === config($configPrefix.'.oauth_client_secret')
            && $request['scope'] === implode(' ', config($configPrefix.'.oauth_scopes')));
        $this->assertArrayNotHasKey('platform.oauth.tokens', session()->all());
        $this->assertIsString(session($handleKey));
    }

    #[DataProvider('portalContexts')]
    public function test_a_transient_refresh_connection_failure_preserves_the_server_side_session(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake(['https://login.zigpaw.test/oauth/token' => Http::failedConnection()]);
        $store = $this->store($storeClass);
        [$handle, $cacheKey] = $this->expire($store, $handleKey);

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session($handleKey));
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[DataProvider('portalContexts')]
    public function test_a_transient_refresh_server_error_preserves_the_server_side_session(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake(['https://login.zigpaw.test/oauth/token' => Http::response(['message' => 'Unavailable'], 503)]);
        $store = $this->store($storeClass);
        [$handle, $cacheKey] = $this->expire($store, $handleKey);

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session($handleKey));
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[DataProvider('portalContexts')]
    public function test_a_malformed_successful_refresh_preserves_the_server_side_session(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake(['https://login.zigpaw.test/oauth/token' => Http::response('not-json', 200, ['Content-Type' => 'text/plain'])]);
        $store = $this->store($storeClass);
        [$handle, $cacheKey] = $this->expire($store, $handleKey);

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session($handleKey));
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[DataProvider('portalContexts')]
    public function test_an_invalid_grant_refresh_forgets_the_server_side_session(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake(['https://login.zigpaw.test/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $store = $this->store($storeClass);
        [, $cacheKey] = $this->expire($store, $handleKey);

        $this->assertNull($store->accessToken());
        $this->assertNull(session($handleKey));
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[DataProvider('portalContexts')]
    public function test_a_refresh_authentication_failure_forgets_the_server_side_session(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake(['https://login.zigpaw.test/oauth/token' => Http::response(['message' => 'Unauthenticated'], 401)]);
        $store = $this->store($storeClass);
        [, $cacheKey] = $this->expire($store, $handleKey);

        $this->assertNull($store->accessToken());
        $this->assertNull(session($handleKey));
        $this->assertFalse(Cache::has($cacheKey));
    }

    #[DataProvider('portalContexts')]
    public function test_a_non_definitive_refresh_bad_request_preserves_the_server_side_session(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake(['https://login.zigpaw.test/oauth/token' => Http::response(['error' => 'invalid_request'], 400)]);
        $store = $this->store($storeClass);
        [$handle, $cacheKey] = $this->expire($store, $handleKey);

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session($handleKey));
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[DataProvider('portalContexts')]
    public function test_it_revokes_the_context_specific_oauth_session_before_forgetting_local_tokens(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        $logoutUrl = 'https://login.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed';
        Http::fake([
            'https://api.zigpaw.test'.$sessionEndpoint => Http::response([
                'data' => ['logout_url' => $logoutUrl],
            ]),
        ]);
        $store = $this->store($storeClass);
        $store->put($this->tokenPayload('active'));

        $this->assertSame($logoutUrl, $store->revoke());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.zigpaw.test'.$sessionEndpoint
            && $request->hasHeader('Authorization', 'Bearer active-access-token')
            && $request->hasHeader('Idempotency-Key'));
        $this->assertNull($store->accessToken());
        $this->assertNull(session($handleKey));
    }

    #[DataProvider('portalContexts')]
    public function test_it_rejects_a_logout_redirect_on_a_different_identity_origin_without_forgetting_tokens(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake([
            'https://api.zigpaw.test'.$sessionEndpoint => Http::response([
                'data' => [
                    'logout_url' => 'https://login.zigpaw.test:444/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed',
                ],
            ]),
        ]);
        $store = $this->store($storeClass);
        $store->put($this->tokenPayload('active'));

        try {
            $store->revoke();
            self::fail('A cross-origin logout redirect should be rejected.');
        } catch (RuntimeException) {
            $this->assertSame('active-access-token', $store->accessToken());
            $this->assertIsString(session($handleKey));
        }
    }

    #[DataProvider('portalContexts')]
    public function test_a_malformed_successful_logout_response_is_bounded_and_preserves_tokens(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        Http::fake([
            'https://api.zigpaw.test'.$sessionEndpoint => Http::response('not-json', 200, ['Content-Type' => 'text/plain']),
        ]);
        $store = $this->store($storeClass);
        $store->put($this->tokenPayload('active'));

        try {
            $store->revoke();
            self::fail('A malformed logout response should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The identity service returned an invalid logout response.', $exception->getMessage());
            $this->assertSame('active-access-token', $store->accessToken());
            $this->assertIsString(session($handleKey));
        }
    }

    #[DataProvider('portalContexts')]
    public function test_unsafe_refresh_configuration_never_receives_credentials(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        $store = $this->store($storeClass);
        [$handle, $cacheKey] = $this->expire($store, $handleKey);
        config()->set($configPrefix.'.auth_url', 'https://login.zigpaw.test.attacker.example');
        Http::fake([
            'https://login.zigpaw.test.attacker.example/*' => Http::response([
                'access_token' => 'stolen',
                'refresh_token' => 'stolen',
                'expires_in' => 900,
            ]),
        ]);

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session($handleKey));
        $this->assertTrue(Cache::has($cacheKey));
        Http::assertNothingSent();
    }

    #[DataProvider('portalContexts')]
    public function test_unsafe_logout_configuration_never_receives_credentials(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        $store = $this->store($storeClass);
        $store->put($this->tokenPayload('active'));
        config()->set($configPrefix.'.api_url', 'https://api.zigpaw.test.attacker.example');
        Http::fake([
            'https://api.zigpaw.test.attacker.example/*' => Http::response([
                'data' => [
                    'logout_url' => 'https://login.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed',
                ],
            ]),
        ]);

        try {
            $store->revoke();
            self::fail('Unsafe logout configuration should be rejected.');
        } catch (RuntimeException) {
            $this->assertSame('active-access-token', $store->accessToken());
            $this->assertIsString(session($handleKey));
        }

        Http::assertNothingSent();
    }

    #[DataProvider('portalContexts')]
    public function test_it_discards_an_encrypted_token_payload_with_an_invalid_shape(
        string $storeClass,
        string $configPrefix,
        string $handleKey,
        string $sessionEndpoint,
    ): void {
        $handle = str_repeat('h', 64);
        session()->put($handleKey, $handle);
        $cacheKey = $this->cacheKey($handle);
        Cache::put($cacheKey, Crypt::encryptString(json_encode([
            'access_token' => 'incomplete-token-envelope',
        ], JSON_THROW_ON_ERROR)), now()->addMinute());

        $this->assertNull($this->store($storeClass)->accessToken());
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertNull(session($handleKey));
    }

    /** @param class-string<PortalAccessTokenStore> $storeClass */
    private function store(string $storeClass): PortalAccessTokenStore
    {
        return app($storeClass);
    }

    /** @return array{access_token: string, refresh_token: string, expires_in: int} */
    private function tokenPayload(string $prefix, int $expiresIn = 900): array
    {
        return [
            'access_token' => $prefix.'-access-token',
            'refresh_token' => $prefix.'-refresh-token',
            'expires_in' => $expiresIn,
        ];
    }

    /** @return array{0: string, 1: string}|null */
    private function expire(PortalAccessTokenStore $store, ?string $handleKey = null): ?array
    {
        $store->put($this->tokenPayload('expired', 1));
        if ($handleKey === null) {
            return null;
        }

        $handle = session($handleKey);
        $this->assertIsString($handle);

        return [$handle, $this->cacheKey($handle)];
    }

    private function cacheKey(string $handle): string
    {
        return 'platform.oauth.tokens:'.hash_hmac('sha256', $handle, (string) config('app.key'));
    }
}
