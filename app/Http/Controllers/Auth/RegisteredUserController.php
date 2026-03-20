<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivationEvent;
use App\Models\InviteCode;
use App\Models\User;
use App\Modules\Season\Services\ActivationTracker;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $invite = InviteCode::findByCode($request->query('invite'));

        return view('auth.register', [
            'inviteCode' => $request->query('invite'),
            'betaMode' => config('beta.enabled'),
            'email' => $invite->email ?? null,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
        ];

        if (config('beta.enabled')) {
            $rules['invite_code'] = ['required', 'string'];
        }

        $request->validate($rules);

        $invite = null;

        if (config('beta.enabled')) {
            $invite = InviteCode::findByCode($request->input('invite_code'));

            if (! $invite || ! $invite->isValidForEmail($request->input('email'))) {
                return back()->withErrors([
                    'invite_code' => __('beta.invalid_invite'),
                ])->withInput();
            }
        }

        // Check if an unactivated user with this email already exists
        $existingUser = User::where('email', $request->input('email'))->first();

        if ($existingUser) {
            if ($existingUser->isActivated()) {
                return back()->withErrors([
                    'email' => __('validation.unique', ['attribute' => __('auth.Email')]),
                ])->withInput();
            }

            // Update name for existing unactivated user and resend activation
            $existingUser->update(['name' => $request->name]);
            $user = $existingUser;
        } else {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            if ($invite) {
                $invite->consume();
            }

            event(new Registered($user));

            app(ActivationTracker::class)->record($user->id, ActivationEvent::EVENT_REGISTERED);
        }

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('activation.sent');
    }
}
