<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class PortalSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $developmentSources = app()->environment(['local', 'testing'])
            ? ' http://localhost:5173 https://localhost:5173 http://127.0.0.1:5173 https://127.0.0.1:5173'
            : '';
        $connectSources = "'self'{$developmentSources}";
        if ($developmentSources !== '') {
            $connectSources .= ' ws://localhost:5173 wss://localhost:5173 ws://127.0.0.1:5173 wss://127.0.0.1:5173';
        }

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "style-src 'self' 'unsafe-inline'{$developmentSources}",
            "script-src 'self' 'nonce-{$nonce}'{$developmentSources}",
            "connect-src {$connectSources}",
        ];

        // Upgrading subresources on a local HTTP site makes Vite assets and
        // brand files unreachable. Production and staging are HTTPS-only and
        // keep the stricter upgrade policy.
        if (app()->environment(['production', 'staging']) || $request->isSecure()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        $policy = implode('; ', $directives);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $response->headers->set('Content-Security-Policy', $policy);

        if (! $request->is('health', 'health/*', 'up')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        if (app()->environment(['production', 'staging']) && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
