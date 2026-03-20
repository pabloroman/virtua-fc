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
        $inviteCode = $request->query('invite');
        $invite = InviteCode::findByCode($inviteCode);

        return view('auth.register', [
            'inviteCode' => $inviteCode,
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
            'invite_code' => ['sometimes', 'nullable', 'string'],
        ];

        $request->validate($rules);

        $invite = null;
        $hasCareerAccess = false;

        if ($request->filled('invite_code')) {
            $invite = InviteCode::findByCode($request->input('invite_code'));

            if ($invite && $invite->isValidForEmail($request->input('email'))) {
                $hasCareerAccess = true;
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

            // Update name and career access for existing unactivated user and resend activation
            $existingUser->update([
                'name' => $request->name,
                'has_career_access' => $hasCareerAccess || $existingUser->has_career_access,
            ]);
            $user = $existingUser;
        } else {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'has_career_access' => $hasCareerAccess,
            ]);

            if ($hasCareerAccess) {
                $invite->consume();
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            event(new Registered($user));

            app(ActivationTracker::class)->record($user->id, ActivationEvent::EVENT_REGISTERED);
        }

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('activation.sent');
    }
}
