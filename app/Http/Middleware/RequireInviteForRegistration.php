<?php

namespace App\Http\Middleware;

use App\Models\InviteCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInviteForRegistration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('beta.enabled')) {
            return $next($request);
        }

        $code = $request->query('invite') ?? $request->input('invite_code');

        if (! $code) {
            return redirect()->route('login')
                ->with('status', __('beta.registration_closed'));
        }

        $invite = InviteCode::findByCode($code);

        if (! $invite || ! $invite->isValid()) {
            return redirect()->route('login')
                ->with('status', __('beta.invalid_invite'));
        }

        return $next($request);
    }
}
