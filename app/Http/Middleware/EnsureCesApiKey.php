<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureCesApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('ces.api_key');

        if (! is_string($expectedKey) || $expectedKey === '') {
            Log::critical('CES API key is not configured.');

            return response()->json(['message' => 'CES API is not configured.'], 503);
        }

        $providedKey = $request->bearerToken();

        if (! is_string($providedKey) || ! hash_equals($expectedKey, $providedKey)) {
            return response()
                ->json(['message' => 'Unauthenticated.'], 401)
                ->header('WWW-Authenticate', 'Bearer');
        }

        return $next($request);
    }
}
