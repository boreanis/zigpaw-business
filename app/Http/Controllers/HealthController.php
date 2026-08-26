<?php

namespace App\Http\Controllers;

use App\Support\PlatformConfiguration;
use App\Support\RequestCorrelation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HealthController extends Controller
{
    private const LOCAL_BUSINESS_SECRET = 'zigpaw-local-business-bff-secret';

    private const LOCAL_CLINICAL_SECRET = 'zigpaw-local-business-clinical-bff-secret';

    public function live(): JsonResponse
    {
        return $this->uncached(response()->json(['status' => 'ok']));
    }

    public function ready(): JsonResponse
    {
        try {
            $ready = Cache::remember('health:readiness', 15, fn (): bool => $this->configurationIsReady()
                && $this->cacheIsReady()
                && $this->platformIsReady());
        } catch (\Throwable) {
            $ready = false;
        }

        return $this->uncached(response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
        ], $ready ? 200 : 503));
    }

    private function configurationIsReady(): bool
    {
        $businessReady = $this->clientIsReady(
            'platform',
            PlatformConfiguration::BUSINESS,
            'business:',
            self::LOCAL_BUSINESS_SECRET,
        );
        $clinicalReady = $this->clientIsReady(
            'platform_clinical',
            PlatformConfiguration::CLINICAL,
            'clinical:',
            self::LOCAL_CLINICAL_SECRET,
        );

        if (! $businessReady || ! $clinicalReady) {
            return false;
        }

        $businessClientId = (string) config('platform.oauth_client_id');
        $clinicalClientId = (string) config('platform_clinical.oauth_client_id');
        $businessClientSecret = (string) config('platform.oauth_client_secret');
        $clinicalClientSecret = (string) config('platform_clinical.oauth_client_secret');

        if (hash_equals($businessClientId, $clinicalClientId)
            || hash_equals($businessClientSecret, $clinicalClientSecret)) {
            return false;
        }

        if (! app()->environment(['production', 'staging'])) {
            return true;
        }

        return config('app.debug') === false
            && config('cache.default') === 'redis'
            && config('session.driver') === 'redis'
            && config('session.encrypt') === true
            && config('session.secure') === true
            && blank(config('session.domain'))
            && str_starts_with((string) config('session.cookie'), '__Host-');
    }

    private function clientIsReady(
        string $configPrefix,
        string $context,
        string $scopePrefix,
        string $localSecret,
    ): bool {
        $clientId = config("{$configPrefix}.oauth_client_id");
        $clientSecret = config("{$configPrefix}.oauth_client_secret");
        $scopes = config("{$configPrefix}.oauth_scopes");

        if (! is_string($clientId) || $clientId === ''
            || ! is_string($clientSecret) || strlen($clientSecret) < 32
            || ! is_array($scopes) || $scopes === []
            || collect($scopes)->contains(fn (mixed $scope): bool => ! is_string($scope) || ! str_starts_with($scope, $scopePrefix))
            || ! PlatformConfiguration::isSafe($context)) {
            return false;
        }

        return app()->environment(['local', 'testing'])
            || ! hash_equals(hash('sha256', $localSecret), $clientSecret);
    }

    private function cacheIsReady(): bool
    {
        $key = 'health:ready:'.Str::uuid();

        try {
            Cache::put($key, 'ready', 10);

            return Cache::get($key) === 'ready';
        } catch (\Throwable) {
            return false;
        } finally {
            try {
                Cache::forget($key);
            } catch (\Throwable) {
                // The failed dependency is already represented by the readiness result.
            }
        }
    }

    private function platformIsReady(): bool
    {
        try {
            $response = Http::baseUrl((string) config('platform.api_url'))
                ->acceptJson()
                ->withHeader('X-Request-ID', RequestCorrelation::id())
                ->connectTimeout(2)
                ->timeout(5)
                // The canonical API ingress exposes its runtime probe at
                // /health. /health/ready belongs to Platform's browser/web
                // runtime and is not available on api.zigpaw.app.
                ->get('/health');

            return $response->successful() && $response->json('status') === 'ok';
        } catch (ConnectionException) {
            return false;
        }
    }

    private function uncached(JsonResponse $response): JsonResponse
    {
        return $response->withHeaders([
            'Cache-Control' => 'no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
