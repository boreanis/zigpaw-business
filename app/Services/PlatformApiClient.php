<?php

namespace App\Services;

use App\Exceptions\PlatformApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PlatformApiClient
{
    /** @return array<string, mixed> */
    public function get(string $path, string $accessToken): array
    {
        try {
            $response = Http::baseUrl((string) config('platform.api_url'))
                ->acceptJson()
                ->withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(8)
                ->get($path);
        } catch (ConnectionException) {
            throw new PlatformApiException(0, 'Zigpaw is unavailable right now. Please try again shortly.');
        }

        if (! $response->successful()) {
            throw new PlatformApiException(
                $response->status(),
                (string) ($response->json('message') ?: 'Zigpaw could not complete that request.'),
            );
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new PlatformApiException(502, 'Zigpaw returned an unexpected response.');
        }

        return $data;
    }
}
