<?php

namespace App\Http\Middleware;

use App\Support\RequestCorrelation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestCorrelation
{
    public const ATTRIBUTE = 'zigpaw.request_id';

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = RequestCorrelation::id($request->header('X-Request-ID'));

        $request->attributes->set(self::ATTRIBUTE, $requestId);
        $request->headers->set('X-Request-ID', $requestId);
        Log::withContext(['request_id' => $requestId]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            Context::forget('request_id');
            Log::withoutContext(['request_id']);
        }
    }
}
