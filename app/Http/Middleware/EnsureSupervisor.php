<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupervisor
{
    /**
     * Restrict /dashboard to users with is_sup set manually in the database.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSup() === true) {
            return $next($request);
        }

        return redirect()->route('whiteboard');
    }
}
