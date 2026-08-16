<?php

namespace Tests\Feature;

use App\Support\PortalAccessTokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PortalAccessTokenStoreTest extends TestCase
{
    public function test_encrypted_tokens_survive_safe_browser_session_rotation(): void
    {
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'active-access-token',
            'refresh_token' => 'active-refresh-token',
            'expires_in' => 900,
        ]);
        $handle = session('platform.oauth.token_handle');

        session()->migrate(destroy: true);

        $this->assertSame($handle, session('platform.oauth.token_handle'));
        $this->assertSame('active-access-token', $store->accessToken());
    }

    public function test_it_refreshes_an_expired_access_token_without_exposing_tokens_to_the_browser(): void
    {
        Http::fake([
            'https://login.zigpaw.test/oauth/token' => Http::response([
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'rotated-refresh-token',
                'expires_in' => 900,
                'token_type' => 'Bearer',
            ]),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 1,
        ]);

        $this->assertSame('fresh-access-token', $store->accessToken());
        Http::assertSent(fn (Request $request): bool => $request['grant_type'] === 'refresh_token'
            && $request['client_secret'] === config('platform.oauth_client_secret'));
        $this->assertArrayNotHasKey('platform.oauth.tokens', session()->all());
    }

    public function test_a_transient_refresh_connection_failure_preserves_the_server_side_session(): void
    {
        Http::fake([
            'https://login.zigpaw.test/oauth/token' => Http::failedConnection(),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 1,
        ]);
        [$handle, $cacheKey] = $this->tokenHandleAndCacheKey();

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session('platform.oauth.token_handle'));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_a_transient_refresh_server_error_preserves_the_server_side_session(): void
    {
        Http::fake([
            'https://login.zigpaw.test/oauth/token' => Http::response(['error' => 'temporarily_unavailable'], 503),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 1,
        ]);
        [$handle, $cacheKey] = $this->tokenHandleAndCacheKey();

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session('platform.oauth.token_handle'));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_a_malformed_successful_refresh_preserves_the_server_side_session(): void
    {
        Http::fake([
            'https://login.zigpaw.test/oauth/token' => Http::response('not-json', 200, ['Content-Type' => 'application/json']),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 1,
        ]);
        [$handle, $cacheKey] = $this->tokenHandleAndCacheKey();

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session('platform.oauth.token_handle'));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_an_invalid_grant_refresh_forgets_the_server_side_session(): void
    {
        Http::fake([
            'https://login.zigpaw.test/oauth/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'invalid-refresh-token',
            'expires_in' => 1,
        ]);
        [, $cacheKey] = $this->tokenHandleAndCacheKey();

        $this->assertNull($store->accessToken());
        $this->assertNull(session('platform.oauth.token_handle'));
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_a_refresh_authentication_failure_forgets_the_server_side_session(): void
    {
        Http::fake([
            'https://login.zigpaw.test/oauth/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 1,
        ]);
        [, $cacheKey] = $this->tokenHandleAndCacheKey();

        $this->assertNull($store->accessToken());
        $this->assertNull(session('platform.oauth.token_handle'));
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_a_non_definitive_refresh_bad_request_preserves_the_server_side_session(): void
    {
        Http::fake([
            'https://login.zigpaw.test/oauth/token' => Http::response(['error' => 'invalid_request'], 400),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 1,
        ]);
        [$handle, $cacheKey] = $this->tokenHandleAndCacheKey();

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session('platform.oauth.token_handle'));
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_it_revokes_the_canonical_oauth_session_before_forgetting_local_tokens(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/vets/session' => Http::response([
                'data' => ['logout_url' => 'https://login.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed'],
            ]),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'active-access-token',
            'refresh_token' => 'active-refresh-token',
            'expires_in' => 900,
        ]);

        $logoutUrl = $store->revoke();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.zigpaw.test/v1/vets/session'
            && $request->hasHeader('Authorization', 'Bearer active-access-token')
            && $request->hasHeader('Idempotency-Key'));
        $this->assertNull($store->accessToken());
        $this->assertSame('https://login.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed', $logoutUrl);
    }

    public function test_it_rejects_a_logout_redirect_on_a_different_identity_origin_without_forgetting_tokens(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/vets/session' => Http::response([
                'data' => ['logout_url' => 'https://login.zigpaw.test:444/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed'],
            ]),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'active-access-token',
            'refresh_token' => 'active-refresh-token',
            'expires_in' => 900,
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            $store->revoke();
        } finally {
            $this->assertSame('active-access-token', $store->accessToken());
        }
    }

    public function test_a_malformed_successful_logout_response_is_bounded_and_preserves_tokens(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/vets/session' => Http::response('not-json', 200, ['Content-Type' => 'application/json']),
        ]);
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'active-access-token',
            'refresh_token' => 'active-refresh-token',
            'expires_in' => 900,
        ]);

        try {
            $store->revoke();
            $this->fail('A malformed logout response should be rejected.');
        } catch (\RuntimeException) {
            $this->assertSame('active-access-token', $store->accessToken());
        }
    }

    public function test_unsafe_platform_configuration_never_receives_refresh_or_logout_credentials(): void
    {
        config()->set('platform.auth_url', 'https://login.zigpaw.test.attacker.example');
        config()->set('platform.api_url', 'https://api.zigpaw.test.attacker.example');
        Http::preventStrayRequests();
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'expired-access-token',
            'refresh_token' => 'refresh-token',
            'expires_in' => 1,
        ]);
        [$handle, $cacheKey] = $this->tokenHandleAndCacheKey();

        $this->assertNull($store->accessToken());
        $this->assertSame($handle, session('platform.oauth.token_handle'));
        $this->assertTrue(Cache::has($cacheKey));

        try {
            $store->revoke();
            $this->fail('Unsafe platform configuration should block logout requests.');
        } catch (\RuntimeException) {
            $this->assertTrue(Cache::has($cacheKey));
        }

        Http::assertNothingSent();
    }

    public function test_it_discards_an_encrypted_token_payload_with_an_invalid_shape(): void
    {
        $handle = str_repeat('h', 64);
        session()->put('platform.oauth.token_handle', $handle);
        $cacheKey = 'platform.oauth.tokens:'.hash_hmac('sha256', $handle, (string) config('app.key'));
        Cache::put($cacheKey, Crypt::encryptString(json_encode([
            'access_token' => 'access-token-without-refresh-metadata',
        ], JSON_THROW_ON_ERROR)));

        $this->assertNull(app(PortalAccessTokenStore::class)->accessToken());
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertNull(session('platform.oauth.token_handle'));
    }

    /** @return array{0: string, 1: string} */
    private function tokenHandleAndCacheKey(): array
    {
        $handle = session('platform.oauth.token_handle');
        $this->assertIsString($handle);

        return [
            $handle,
            'platform.oauth.tokens:'.hash_hmac('sha256', $handle, (string) config('app.key')),
        ];
    }
}
