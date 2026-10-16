<?php

namespace Tests\Feature;

use App\Support\PortalAccessTokenStore;
use Illuminate\Cache\ArrayStore;
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
            'https://auth.zigpaw.test/oauth/token' => Http::response([
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
            && $request->url() === 'https://auth.zigpaw.test/oauth/token'
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

    public function test_a_failed_rotation_store_recovers_the_exact_persisted_operation_on_retry(): void
    {
        $backing = new class extends ArrayStore
        {
            public bool $failNextPut = false;

            public function put($key, $value, $seconds): bool
            {
                if ($this->failNextPut) {
                    $this->failNextPut = false;

                    return false;
                }

                return parent::put($key, $value, $seconds);
            }
        };
        Cache::extend('fault-injected', fn () => Cache::repository($backing));
        config()->set('cache.stores.fault-injected', ['driver' => 'fault-injected']);
        Cache::setDefaultDriver('fault-injected');
        $store = app(PortalAccessTokenStore::class);
        $store->put(['access_token' => 'expired', 'refresh_token' => 'old-refresh', 'expires_in' => 1]);
        $operations = [];
        Http::fake(function (Request $request) use (&$operations, $backing) {
            $operations[] = $request->data();
            if (count($operations) === 1) {
                $backing->failNextPut = true;
            }

            return Http::response(['access_token' => 'fresh', 'refresh_token' => 'new-refresh', 'expires_in' => 900, 'token_type' => 'Bearer']);
        });

        try {
            $store->accessToken();
            self::fail('A failed token write must not report refresh success.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The secure session service is unavailable.', $exception->getMessage());
        }
        self::assertSame('fresh', $store->accessToken());
        self::assertCount(2, $operations);
        self::assertSame($operations[0]['refresh_operation'], $operations[1]['refresh_operation']);
        self::assertSame($operations[0]['session_binding'], $operations[1]['session_binding']);
        self::assertSame('old-refresh', $operations[1]['refresh_token']);
    }

    public function test_logout_during_rotation_cannot_restore_a_deleted_token_handle(): void
    {
        $store = app(PortalAccessTokenStore::class);
        $store->put(['access_token' => 'expired', 'refresh_token' => 'old-refresh', 'expires_in' => 1]);
        Http::fake(function () use ($store) {
            $store->forget();

            return Http::response(['access_token' => 'fresh', 'refresh_token' => 'new-refresh', 'expires_in' => 900, 'token_type' => 'Bearer']);
        });

        self::assertNull($store->accessToken());
        self::assertNull($store->accessToken());
        self::assertNull(session('platform.oauth.token_handle'));
    }

    public function test_it_revokes_the_canonical_oauth_session_before_forgetting_local_tokens(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/session' => Http::response([
                'data' => ['logout_url' => 'https://auth.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed'],
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
        $this->assertSame('https://auth.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed', $logoutUrl);
    }

    public function test_it_rejects_a_logout_redirect_on_a_different_identity_origin_without_forgetting_tokens(): void
    {
        Http::fake([
            'https://api.zigpaw.test/v1/business/session' => Http::response([
                'data' => ['logout_url' => 'https://auth.zigpaw.test:444/session/end/019f5a00-0000-7000-8000-000000000099?nonce=nonce&expires=1786400000&signature=signed'],
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
