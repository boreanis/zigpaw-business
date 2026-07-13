<?php

namespace App\Services;

use App\Exceptions\PlatformApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PlatformApiClient
{
    /** @return array<string, mixed> */
    public function get(string $path, string $accessToken, ?string $organizationId = null): array
    {
        try {
            $response = $this->request($accessToken, $organizationId)->get($path);
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

    private function request(string $accessToken, ?string $organizationId): PendingRequest
    {
        $request = Http::baseUrl((string) config('platform.api_url'))
            ->acceptJson()
            ->withToken($accessToken)
            ->connectTimeout(3)
            ->timeout(8);

        return $organizationId ? $request->withHeader('X-Zigpaw-Organization-ID', $organizationId) : $request;
    }
}
