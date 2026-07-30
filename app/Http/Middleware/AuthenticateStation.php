<?php

namespace App\Http\Middleware;

use App\Models\Station;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uploader agents authenticate with a per-station bearer token; a
 * revoked token dies here. The matched station rides on the request.
 */
class AuthenticateStation
{
    public function handle(Request $request, Closure $next): Response
    {
        $station = Station::findByToken((string) $request->header('X-Station-Token'));

        if ($station === null) {
            return response()->json(['message' => 'Invalid or revoked station token.'], 401);
        }

        $station->forceFill(['last_seen_at' => now()])->save();
        $request->attributes->set('station', $station);

        return $next($request);
    }
}
