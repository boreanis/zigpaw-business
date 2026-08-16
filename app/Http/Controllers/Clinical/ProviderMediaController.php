<?php

namespace App\Http\Controllers\Clinical;

use App\Exceptions\PlatformApiException;
use App\Http\Controllers\Controller;
use App\Services\PlatformApiClient;
use App\Support\PortalAccessTokenStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class ProviderMediaController extends Controller
{
    public function __invoke(
        string $grantId,
        int $mediaId,
        PlatformApiClient $api,
        PortalAccessTokenStore $tokens,
    ): Response|RedirectResponse {
        $accessToken = $tokens->accessToken();
        $organizationId = session('portal.organization_id');

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($organizationId) || $organizationId === '') {
            return redirect()->route('dashboard')->with('error', 'Your secure session has ended. Please sign in again.');
        }

        try {
            $upstream = $api->providerMediaDownload($accessToken, $organizationId, $grantId, $mediaId);
        } catch (PlatformApiException $exception) {
            if ($exception->status === 401) {
                $tokens->forget();
                session()->forget(['portal.organization_id', 'portal.organization_name']);
            }

            return redirect()->route('patients.show', ['grantId' => $grantId])->with(
                'error',
                match ($exception->status) {
                    403 => 'This family has not shared that file with this clinical team.',
                    404 => 'That shared file is no longer available.',
                    default => $exception->getMessage(),
                },
            );
        }

        $response = response($upstream->body(), $upstream->status());
        foreach (['Content-Type', 'Content-Length', 'Content-Disposition', 'ETag', 'Last-Modified'] as $header) {
            $value = $upstream->header($header);
            if ($value !== '') {
                $response->headers->set($header, $value);
            }
        }

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
