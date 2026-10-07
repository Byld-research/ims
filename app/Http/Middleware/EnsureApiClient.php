<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API requests (SPEC 7a): the token must belong to an active API client and carry the
 * ability the route needs. Anything else is answered as unauthenticated or forbidden.
 */
class EnsureApiClient
{
    public function handle(Request $request, Closure $next, string $ability = 'read'): Response
    {
        $client = $request->user();

        if (! $client instanceof ApiClient || ! $client->is_active) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $client->tokenCan($ability)) {
            return response()->json(['message' => 'This token does not allow this request.'], 403);
        }

        return $next($request);
    }
}
