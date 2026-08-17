<?php

namespace App\Http\Middleware;

use App\Support\ClinicalPortalAccessTokenStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireClinicalWorkspace
{
    public function __construct(private readonly ClinicalPortalAccessTokenStore $tokens) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tokens->accessToken()) {
            return redirect()->route('clinical.dashboard')->with(
                'error',
                'Your secure clinical session has ended. Please sign in again.',
            );
        }

        return $next($request);
    }
}
