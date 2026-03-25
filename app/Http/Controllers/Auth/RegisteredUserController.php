<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationEvent;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Modules\Season\Services\ActivationTracker;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $invite = InviteCode::findByCode($request->query('invite'));

        if ($invite) {
            $name = WaitlistEntry::whereEmail($invite->email)->first()?->name;
            return view('auth.register-career-mode', [
                'inviteCode' => $request->query('invite'),
                'betaMode' => config('beta.enabled'),
                'name' => $name ?? null,
                'email' => $invite->email ?? null,
            ]);
        } else {
            return view('auth.register-tournament-mode');
        }
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeCareerModeRegistration(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invite_code' => ['required', 'string'],
        ];

        $request->validate($rules);

        $invite = InviteCode::findByCode($request->input('invite_code'));

        if (! $invite || ! $invite->isValidForEmail($request->input('email'))) {
            return back()->withErrors([
                'invite_code' => __('beta.invalid_invite'),
            ])->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'has_career_access' => true,
        ]);

        $invite->consume();
        $user->forceFill(['email_verified_at' => now()])->save();

        event(new Registered($user));

        app(ActivationTracker::class)->record($user->id, ActivationEvent::EVENT_REGISTERED);

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function storeTournamentModeRegistration(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ];

        $request->validate($rules);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'has_career_access' => false,
            'has_tournament_access' => true,
        ]);

        event(new Registered($user));

        app(ActivationTracker::class)->record($user->id, ActivationEvent::EVENT_REGISTERED);

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('activation.sent');
    }
}
