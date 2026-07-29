<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-only actions: removals, reports, settings, deletions. Scanner
 * accounts digitize and pack; they never manage the collection.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Administrators only.');

        return $next($request);
    }
}
