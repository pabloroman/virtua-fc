<?php

namespace Tests\Feature\Auth;

use App\Models\InviteCode;
use App\Models\User;
use App\Notifications\ActivateAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('beta.enabled', false);
    }

    /**
     * Create an unactivated (passwordless-onboarding) user and trigger the
     * activation email, mirroring the state legacy accounts land in. The
     * open-signup funnel that used to create these was retired with the World
     * Cup, but the activation-via-password-reset mechanism still serves them.
     */
    private function sendActivationTo(string $email, string $name = 'New User'): User
    {
        $user = User::factory()->unverified()->create([
            'name' => $name,
            'email' => $email,
        ]);

        Password::sendResetLink(['email' => $email]);

        return $user;
    }

    // --- Activation via password reset token ---

    public function test_activation_sets_password_and_verifies_email(): void
    {
        Notification::fake();

        $user = $this->sendActivationTo('activate@example.com');

        Notification::assertSentTo($user, ActivateAccount::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'my-new-password',
                'password_confirmation' => 'my-new-password',
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect(route('dashboard'));

            $user->refresh();
            $this->assertNotNull($user->password);
            $this->assertNotNull($user->email_verified_at);

            return true;
        });
    }

    public function test_activation_logs_in_user(): void
    {
        Notification::fake();

        $user = $this->sendActivationTo('login@example.com');

        Notification::assertSentTo($user, ActivateAccount::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'my-new-password',
                'password_confirmation' => 'my-new-password',
            ]);

            $this->assertAuthenticatedAs($user);

            return true;
        });
    }

    // --- Login blocked for unactivated users ---

    public function test_unactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'pending@example.com',
        ]);

        $response = $this->post('/login', [
            'email' => 'pending@example.com',
            'password' => 'anything',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // --- Career mode registration with invite code ---

    public function test_career_mode_registration_sets_email_verified_at(): void
    {
        config()->set('beta.enabled', false);

        InviteCode::create([
            'code' => 'TESTCODE',
            'email' => 'invited@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $this->post(route('register.career-mode'), [
            'name' => 'Invited User',
            'email' => 'invited@example.com',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
            'invite_code' => 'TESTCODE',
        ]);

        $user = User::where('email', 'invited@example.com')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->has_tournament_access);
    }

    // --- Forgot password sends correct notification per state ---

    public function test_forgot_password_sends_activation_for_unactivated_user(): void
    {
        $this->sendActivationTo('unactivated@example.com', 'Unactivated');

        // Advance past the password broker throttle window
        $this->travel(2)->minutes();
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'unactivated@example.com']);

        $user = User::where('email', 'unactivated@example.com')->first();
        Notification::assertSentTo($user, ActivateAccount::class);
    }
}
