<?php

namespace App\Http\Controllers\OAuth;

use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\ClinicalReturnDestination;
use App\Support\ClinicalSubmissionRetry;
use App\Support\PlatformConfiguration;
use App\Support\RequestCorrelation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClinicalPortalOAuthController
{
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('platform_clinical.oauth_client_id');
        $clientSecret = config('platform_clinical.oauth_client_secret');
        $redirectUri = config('platform_clinical.oauth_redirect_uri');
        $canonicalPortalUrl = rtrim((string) config('app.url'), '/');
        abort_unless(is_string($clientId) && $clientId !== ''
            && is_string($clientSecret) && strlen($clientSecret) >= 32
            && is_string($redirectUri)
            && PlatformConfiguration::isSafe(PlatformConfiguration::CLINICAL),
            503,
            'The clinical workspace has not been connected to Zigpaw yet.',
        );

        if (! hash_equals($canonicalPortalUrl, $request->getSchemeAndHttpHost())) {
            return redirect()->away($canonicalPortalUrl.'/clinical/auth/login', 308);
        }

        $state = Str::random(64);
        $verifier = Str::random(96);
        app(ClinicalReturnDestination::class)->begin(ClinicalReturnDestination::validHandle($request->query('resume')), $state, $verifier);

        $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('platform_clinical.oauth_scopes')),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away(config('platform_clinical.auth_url').'/oauth/authorize?'.$query);
    }

    public function callback(Request $request, ClinicalPortalAccessTokenStore $tokens): RedirectResponse
    {
        abort_unless(
            PlatformConfiguration::isSafe(PlatformConfiguration::CLINICAL),
            503,
            'The clinical workspace has not been connected to Zigpaw yet.',
        );

        $continuation = app(ClinicalReturnDestination::class);
        $authorization = $continuation->authorization($request->string('state')->toString());

        if ($authorization === null) {
            return redirect()->route('clinical.dashboard')->with('error', 'That clinical sign-in link is no longer valid. Please try again.');
        }

        $code = $request->string('code')->toString();
        if ($code === '') {
            return redirect()->route('clinical.dashboard')->with('error', 'Zigpaw did not complete clinical sign-in. Please try again.');
        }

        try {
            $response = Http::asForm()->acceptJson()
                ->withHeader('X-Request-ID', RequestCorrelation::id())
                ->connectTimeout(3)
                ->timeout(8)
                ->post(rtrim((string) config('platform_clinical.auth_url'), '/').'/oauth/token', [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('platform_clinical.oauth_client_id'),
                    'client_secret' => config('platform_clinical.oauth_client_secret'),
                    'redirect_uri' => config('platform_clinical.oauth_redirect_uri'),
                    'code_verifier' => $authorization['verifier'],
                    'code' => $code,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Clinical portal OAuth token exchange was unavailable.', ['exception' => $exception::class]);

            return redirect()->route('clinical.dashboard')->with('error', 'Clinical sign-in is temporarily unavailable. Please try again in a moment.');
        }

        if (! $response->successful()) {
            return redirect()->route('clinical.dashboard')->with('error', 'Zigpaw could not complete clinical sign-in. Please try again.');
        }

        try {
            $payload = $response->json();
            if (! is_array($payload)) {
                throw new \InvalidArgumentException('The authorization server returned an invalid token response.');
            }

            $tokens->put($payload);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            Log::error('Clinical portal OAuth token exchange returned an invalid response.', ['exception' => $exception::class]);

            return redirect()->route('clinical.dashboard')->with('error', 'Zigpaw could not complete clinical sign-in. Please try again.');
        }

        $request->session()->regenerate();
        $resume = $continuation->complete($authorization['target']);

        return redirect()->route('clinical.dashboard', $resume === null ? [] : ['resume' => $resume]);
    }

    public function logout(Request $request, ClinicalPortalAccessTokenStore $tokens): RedirectResponse
    {
        $logoutUrl = null;

        try {
            $logoutUrl = $tokens->revoke();
        } catch (\Throwable $exception) {
            Log::warning('Clinical portal token revocation failed during local sign-out.', ['exception' => $exception::class]);
        } finally {
            try {
                $tokens->forget();
            } catch (\Throwable $exception) {
                Log::warning('Clinical portal token cleanup failed during local sign-out.', ['exception' => $exception::class]);
            } finally {
                $request->session()->forget([
                    'clinical_portal.organization_id',
                    'clinical_portal.organization_name',
                    ClinicalSubmissionRetry::SESSION_KEY,
                    ClinicalReturnDestination::BROWSER_CONTEXT,
                ]);
                $request->session()->regenerateToken();
            }
        }

        if ($logoutUrl === null) {
            return redirect()->route('clinical.dashboard')->with('status', 'You are signed out of the clinical workspace on this device.');
        }

        return redirect()->away($logoutUrl);
    }
}
