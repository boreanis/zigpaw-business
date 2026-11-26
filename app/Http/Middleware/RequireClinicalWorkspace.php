<?php

namespace App\Http\Middleware;

use App\Support\ClinicalPortalAccessTokenStore;
use App\Support\ClinicalReturnDestination;
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
            $resume = app(ClinicalReturnDestination::class)->rememberRequest($request);

            return redirect()->route('clinical.dashboard', $resume === null ? [] : ['resume' => $resume])->with(
                'error',
                'Your secure clinical session has ended. Please sign in again.',
            );
        }

        return $next($request);
    }
}
