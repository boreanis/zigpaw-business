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

    public function test_it_discards_a_malformed_server_side_token_envelope(): void
    {
        $store = app(PortalAccessTokenStore::class);
        $store->put([
            'access_token' => 'active-access-token',
            'refresh_token' => 'active-refresh-token',
            'expires_in' => 900,
        ]);
        $handle = session('platform.oauth.token_handle');
        $this->assertIsString($handle);
        $cacheKey = 'platform.oauth.tokens:'.hash_hmac('sha256', $handle, (string) config('app.key'));
        Cache::put($cacheKey, Crypt::encryptString(json_encode([
            'access_token' => 'incomplete-token-envelope',
        ], JSON_THROW_ON_ERROR)), now()->addMinute());

        $this->assertNull($store->accessToken());
        $this->assertFalse(Cache::has($cacheKey));
        $this->assertNull(session('platform.oauth.token_handle'));
    }

    public function test_it_revokes_the_canonical_oauth_session_before_forgetting_local_tokens(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/session' => Http::response([
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
            && $request->url() === 'https://api.zigpaw.test/v1/business/session'
            && $request->hasHeader('Authorization', 'Bearer active-access-token')
            && $request->hasHeader('Idempotency-Key'));
        $this->assertNull($store->accessToken());
        $this->assertSame('https://login.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed', $logoutUrl);
    }

    public function test_it_rejects_a_logout_redirect_on_a_different_identity_origin_without_forgetting_tokens(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/session' => Http::response([
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
}
