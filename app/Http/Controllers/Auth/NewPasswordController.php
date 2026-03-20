<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationEvent;
use App\Modules\Season\Services\ActivationTracker;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $isActivation = false;
        $activatedUser = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request, &$isActivation, &$activatedUser) {
                $isActivation = ! $user->isActivated();

                $attributes = [
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ];

                if ($isActivation) {
                    $attributes['email_verified_at'] = now();
                }

                $user->forceFill($attributes)->save();

                if ($isActivation) {
                    app(ActivationTracker::class)->record($user->id, ActivationEvent::EVENT_EMAIL_VERIFIED);
                    $activatedUser = $user;
                }

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET && $activatedUser) {
            Auth::login($activatedUser);

            return redirect()->route('dashboard');
        }

        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
