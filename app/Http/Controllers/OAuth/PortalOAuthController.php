<?php

namespace App\Http\Controllers\OAuth;

use App\Support\PlatformConfiguration;
use App\Support\PortalAccessTokenStore;
use App\Support\RequestCorrelation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PortalOAuthController
{
    private const STATE_KEY = 'platform.oauth.state';

    private const VERIFIER_KEY = 'platform.oauth.verifier';

    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('platform.oauth_client_id');
        $clientSecret = config('platform.oauth_client_secret');
        $redirectUri = config('platform.oauth_redirect_uri');
        $canonicalPortalUrl = rtrim((string) config('app.url'), '/');
        abort_unless(is_string($clientId) && $clientId !== ''
            && is_string($clientSecret) && strlen($clientSecret) >= 32
            && is_string($redirectUri)
            && PlatformConfiguration::isSafe(PlatformConfiguration::BUSINESS),
            503,
            'This portal has not been connected to Zigpaw yet.',
        );

        if (! hash_equals($canonicalPortalUrl, $request->getSchemeAndHttpHost())) {
            return redirect()->away($canonicalPortalUrl.'/auth/login', 308);
        }

        $state = Str::random(64);
        $verifier = Str::random(96);
        $request->session()->put([self::STATE_KEY => $state, self::VERIFIER_KEY => $verifier]);

        $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('platform.oauth_scopes')),
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away(config('platform.auth_url').'/oauth/authorize?'.$query);
    }

    public function callback(Request $request, PortalAccessTokenStore $tokens): RedirectResponse
    {
        abort_unless(
            PlatformConfiguration::isSafe(PlatformConfiguration::BUSINESS),
            503,
            'This portal has not been connected to Zigpaw yet.',
        );

        $state = $request->string('state')->toString();
        $expectedState = $request->session()->pull(self::STATE_KEY);
        $verifier = $request->session()->pull(self::VERIFIER_KEY);

        if (! is_string($expectedState) || ! hash_equals($expectedState, $state) || ! is_string($verifier)) {
            return redirect()->route('dashboard')->with('error', 'That sign-in link is no longer valid. Please try again.');
        }

        $code = $request->string('code')->toString();
        if ($code === '') {
            return redirect()->route('dashboard')->with('error', 'Zigpaw did not complete sign-in. Please try again.');
        }

        try {
            $response = Http::asForm()->acceptJson()
                ->withHeader('X-Request-ID', RequestCorrelation::id())
                ->connectTimeout(3)
                ->timeout(8)
                ->post(config('platform.auth_url').'/oauth/token', [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('platform.oauth_client_id'),
                    'client_secret' => config('platform.oauth_client_secret'),
                    'redirect_uri' => config('platform.oauth_redirect_uri'),
                    'code_verifier' => $verifier,
                    'code' => $code,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Portal OAuth token exchange was unavailable.', ['exception' => $exception::class]);

            return redirect()->route('dashboard')->with('error', 'Sign-in is temporarily unavailable. Please try again in a moment.');
        }

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', 'Zigpaw could not complete sign-in. Please try again.');
        }

        try {
            $tokens->put($response->json());
        } catch (\InvalidArgumentException|\JsonException $exception) {
            Log::error('Portal OAuth token exchange returned an invalid response.', ['exception' => $exception::class]);

            return redirect()->route('dashboard')->with('error', 'Zigpaw could not complete sign-in. Please try again.');
        }

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request, PortalAccessTokenStore $tokens): RedirectResponse
    {
        $logoutUrl = null;

        try {
            $logoutUrl = $tokens->revoke();
        } catch (\RuntimeException $exception) {
            Log::warning('Upstream portal token revocation failed during local sign-out.', ['exception' => $exception::class]);
        } finally {
            $tokens->forget();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($logoutUrl === null) {
            return redirect()->route('dashboard')->with('status', 'You are signed out on this device.');
        }

        return redirect()->away($logoutUrl);
    }
}
