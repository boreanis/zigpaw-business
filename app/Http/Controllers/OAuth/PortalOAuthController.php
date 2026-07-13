<?php

namespace App\Http\Controllers\OAuth;

use App\Support\PortalAccessTokenStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PortalOAuthController
{
    private const STATE_KEY = 'platform.oauth.state';

    private const VERIFIER_KEY = 'platform.oauth.verifier';

    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('platform.oauth_client_id');
        abort_unless(is_string($clientId) && $clientId !== '', 503, 'This portal has not been connected to Zigpaw yet.');

        $state = Str::random(64);
        $verifier = Str::random(96);
        $request->session()->put([self::STATE_KEY => $state, self::VERIFIER_KEY => $verifier]);

        $challenge = strtr(rtrim(base64_encode(hash('sha256', $verifier, true)), '='), '+/', '-_');
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => route('oauth.callback'),
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

        $response = Http::asForm()->acceptJson()->connectTimeout(3)->timeout(8)->post(config('platform.auth_url').'/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('platform.oauth_client_id'),
            'redirect_uri' => route('oauth.callback'),
            'code_verifier' => $verifier,
            'code' => $code,
        ]);

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', 'Zigpaw could not complete sign-in. Please try again.');
        }

        $tokens->put($response->json());

        return redirect()->route('dashboard');
    }

    public function logout(Request $request, PortalAccessTokenStore $tokens): RedirectResponse
    {
        $tokens->forget();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard');
    }
}
