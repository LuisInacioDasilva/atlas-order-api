<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.atlas.token', '');
        if ($configured === '') {
            return response()->json(['error' => 'API não configurada.'], 503);
        }
        if (! hash_equals($configured, (string) $request->bearerToken())) {
            return response()->json(['error' => 'Não autorizado.'], 401);
        }

        return $next($request);
    }
}
