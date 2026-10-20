<?php

namespace Tests\Feature;

use App\Http\Controllers\OAuth\ClinicalPortalOAuthController;
use App\Http\Controllers\OAuth\PortalOAuthController;
use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\ClinicalSubmissionRetry;
use App\Support\PortalAccessTokenStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PortalLogoutRecoveryTest extends TestCase
{
    #[DataProvider('cleanupFailures')]
    public function test_local_logout_completes_when_token_cleanup_fails(string $context, string $failure, bool $upstreamSucceeds): void
    {
        $backing = $this->faultingCache();
        app(PortalAccessTokenStore::class)->put(['access_token' => 'business-token', 'refresh_token' => 'business-refresh', 'expires_in' => 900]);
        app(ClinicalPortalAccessTokenStore::class)->put(['access_token' => 'clinical-token', 'refresh_token' => 'clinical-refresh', 'expires_in' => 900]);
        session()->put([
            'portal.organization_id' => 'synthetic-management-organization',
            'portal.organization_name' => 'Synthetic management organization',
            'clinical_portal.organization_id' => 'synthetic-clinical-organization',
            'clinical_portal.organization_name' => 'Synthetic clinic',
            ClinicalSubmissionRetry::SESSION_KEY => ['synthetic-context' => ['key' => 'synthetic-key']],
        ]);
        session()->regenerateToken();
        $sessionId = session()->getId();
        $csrfToken = session()->token();
        $clinical = $context === 'clinical';
        $store = app($clinical ? ClinicalPortalAccessTokenStore::class : PortalAccessTokenStore::class);
        $handleKey = $clinical ? 'platform.oauth.clinical_token_handle' : 'platform.oauth.token_handle';
        $cacheKey = 'platform.oauth.tokens:'.hash_hmac('sha256', session($handleKey), (string) config('app.key'));
        Http::fake(['https://api.zigpaw.test/*' => $upstreamSucceeds
            ? Http::response(['data' => ['logout_url' => 'https://auth.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=synthetic&expires=1786400000&signature=synthetic']])
            : Http::response([], 503)]);
        $backing->failure = $failure;
        $request = Request::create($clinical ? '/clinical/auth/logout' : '/auth/logout', 'POST');
        $request->setLaravelSession(session()->driver());

        $controller = app($clinical ? ClinicalPortalOAuthController::class : PortalOAuthController::class);
        $response = $controller->logout($request, $store);

        $expectedRedirect = $upstreamSucceeds && $failure === 'delete-false'
            ? 'https://auth.zigpaw.test/session/end/019f5a00-0000-7000-8000-000000000099?nonce=synthetic&expires=1786400000&signature=synthetic'
            : route($clinical ? 'clinical.dashboard' : 'dashboard');
        self::assertSame($expectedRedirect, $response->getTargetUrl());
        self::assertNull(session($handleKey));
        self::assertNull($store->accessToken());
        self::assertNotSame($csrfToken, session()->token());
        self::assertNull(session('clinical_portal.organization_id'));
        self::assertNull(session('clinical_portal.organization_name'));
        self::assertNull(session(ClinicalSubmissionRetry::SESSION_KEY));
        if ($clinical) {
            self::assertSame($sessionId, session()->getId());
            self::assertSame('synthetic-management-organization', session('portal.organization_id'));
            self::assertSame('Synthetic management organization', session('portal.organization_name'));
            self::assertSame('business-token', app(PortalAccessTokenStore::class)->accessToken());
        } else {
            self::assertNotSame($sessionId, session()->getId());
            self::assertNull(session('portal.organization_id'));
            self::assertNull(session('portal.organization_name'));
            self::assertNull(session('platform.oauth.clinical_token_handle'));
        }
        self::assertTrue(Cache::has($cacheKey), 'The failed deletion fixture must leave an inaccessible cache entry.');
    }

    public static function cleanupFailures(): array
    {
        $cases = [];
        foreach (['business', 'clinical'] as $context) {
            foreach (['lock-timeout', 'delete-exception', 'delete-false'] as $failure) {
                foreach ([false, true] as $upstreamSucceeds) {
                    $cases[$context.'-'.$failure.'-'.(int) $upstreamSucceeds] = [$context, $failure, $upstreamSucceeds];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('tokenContexts')]
    public function test_late_refresh_cannot_restore_the_handle_after_failed_cache_cleanup(string $storeClass, string $handleKey): void
    {
        $backing = $this->faultingCache();
        $store = app($storeClass);
        $store->put(['access_token' => 'expired-token', 'refresh_token' => 'old-refresh', 'expires_in' => 1]);
        Http::fake(function () use ($backing, $store) {
            $backing->failure = 'delete-exception';
            try {
                $store->forget();
                self::fail('The deletion fixture must fail.');
            } catch (RuntimeException) {
                // A late response must still be fenced after local detachment.
            }

            return Http::response(['access_token' => 'late-token', 'refresh_token' => 'late-refresh', 'expires_in' => 900]);
        });

        self::assertNull($store->accessToken());
        self::assertNull(session($handleKey));
        self::assertNull($store->accessToken());
    }

    public static function tokenContexts(): array
    {
        return [
            [PortalAccessTokenStore::class, 'platform.oauth.token_handle'],
            [ClinicalPortalAccessTokenStore::class, 'platform.oauth.clinical_token_handle'],
        ];
    }

    private function faultingCache(): ArrayStore
    {
        $backing = new class extends ArrayStore
        {
            public ?string $failure = null;

            public function lock($name, $seconds = 0, $owner = null)
            {
                if ($this->failure === 'lock-timeout') {
                    throw new LockTimeoutException;
                }

                return parent::lock($name, $seconds, $owner);
            }

            public function forget($key): bool
            {
                if ($this->failure === 'delete-exception') {
                    throw new RuntimeException('Synthetic secure-store failure.');
                }
                if ($this->failure === 'delete-false') {
                    return false;
                }

                return parent::forget($key);
            }
        };
        Cache::extend('logout-faults', fn () => Cache::repository($backing));
        config()->set('cache.stores.logout-faults', ['driver' => 'logout-faults']);
        Cache::setDefaultDriver('logout-faults');

        return $backing;
    }
}
