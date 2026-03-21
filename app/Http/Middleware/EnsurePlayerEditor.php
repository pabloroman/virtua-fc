<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayerEditor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin && ! $request->user()?->can_edit_players) {
            abort(403);
        }

        return $next($request);
    }
}
