<?php

namespace App\Http\Middleware;

use App\Support\PortalAccessTokenStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePortalWorkspace
{
    public function __construct(private readonly PortalAccessTokenStore $tokens) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tokens->accessToken()) {
            return redirect()->route('dashboard')->with('error', 'Your secure session has ended. Please sign in again.');
        }

        $organizationId = $request->session()->get('portal.organization_id');

        if (! is_string($organizationId) || $organizationId === '') {
            return redirect()->route('dashboard')->with('error', 'Choose a clinical organisation before opening patient records.');
        }

        return $next($request);
    }
}
