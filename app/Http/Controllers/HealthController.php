<?php

namespace App\Http\Controllers;

use App\Support\RequestCorrelation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HealthController extends Controller
{
    private const LOCAL_DEVELOPMENT_SECRET = 'zigpaw-local-business-bff-secret';

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
        $clientId = config('platform.oauth_client_id');
        $clientSecret = config('platform.oauth_client_secret');
        $scopes = config('platform.oauth_scopes');

        if (! is_string($clientId) || $clientId === ''
            || ! is_string($clientSecret) || strlen($clientSecret) < 32
            || ! is_array($scopes) || $scopes === []) {
            return false;
        }

        if (! app()->environment(['local', 'testing'])
            && hash_equals(hash('sha256', self::LOCAL_DEVELOPMENT_SECRET), $clientSecret)) {
            return false;
        }

        if (! app()->environment(['production', 'staging'])) {
            return true;
        }

        $secureConfiguration = config('app.debug') === false
            && str_starts_with((string) config('app.url'), 'https://')
            && str_starts_with((string) config('platform.api_url'), 'https://')
            && str_starts_with((string) config('platform.auth_url'), 'https://')
            && config('cache.default') === 'redis'
            && config('session.driver') === 'redis'
            && config('session.encrypt') === true
            && config('session.secure') === true
            && blank(config('session.domain'))
            && str_starts_with((string) config('session.cookie'), '__Host-');

        if (! $secureConfiguration || ! app()->isProduction()) {
            return $secureConfiguration;
        }

        return str_starts_with((string) config('platform.api_url'), 'https://api.zigpaw.app')
            && str_starts_with((string) config('platform.auth_url'), 'https://login.zigpaw.app');
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
                ->get('/health/ready');

            return $response->successful() && $response->json('status') === 'ready';
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
