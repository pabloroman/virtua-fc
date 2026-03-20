<?php

namespace Tests\Feature\Auth;

use App\Models\InviteCode;
use App\Models\User;
use App\Notifications\ActivateAccount;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('beta.enabled', false);
    }

    // --- Tournament registration creates unactivated user ---

    public function test_registration_creates_user_with_null_password(): void
    {
        Notification::fake();

        $this->post(route('register.tournament-mode'), [
            'name' => 'New User',
            'email' => 'new@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'name' => 'New User',
        ]);

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNull($user->password);
        $this->assertNull($user->email_verified_at);
    }

    public function test_registration_sends_activation_email(): void
    {
        Notification::fake();

        $this->post(route('register.tournament-mode'), [
            'name' => 'New User',
            'email' => 'new@example.com',
        ]);

        $user = User::where('email', 'new@example.com')->first();
        Notification::assertSentTo($user, ActivateAccount::class);
    }

    public function test_registration_redirects_to_activation_sent(): void
    {
        Notification::fake();

        $response = $this->post(route('register.tournament-mode'), [
            'name' => 'New User',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('activation.sent'));
    }

    public function test_registration_does_not_log_in_user(): void
    {
        Notification::fake();

        $this->post(route('register.tournament-mode'), [
            'name' => 'New User',
            'email' => 'new@example.com',
        ]);

        $this->assertGuest();
    }

    // --- Activation via password reset token ---

    public function test_activation_sets_password_and_verifies_email(): void
    {
        Notification::fake();

        $this->post(route('register.tournament-mode'), [
            'name' => 'New User',
            'email' => 'activate@example.com',
        ]);

        $user = User::where('email', 'activate@example.com')->first();

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

        $this->post(route('register.tournament-mode'), [
            'name' => 'New User',
            'email' => 'login@example.com',
        ]);

        $user = User::where('email', 'login@example.com')->first();

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
        Notification::fake();

        $this->post(route('register.tournament-mode'), [
            'name' => 'Pending User',
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
        $this->assertTrue($user->has_career_access);
    }

    // --- Forgot password sends correct notification per state ---

    public function test_forgot_password_sends_activation_for_unactivated_user(): void
    {
        Notification::fake();

        $this->post(route('register.tournament-mode'), [
            'name' => 'Unactivated',
            'email' => 'unactivated@example.com',
        ]);

        // Advance past the password broker throttle window
        $this->travel(2)->minutes();
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'unactivated@example.com']);

        $user = User::where('email', 'unactivated@example.com')->first();
        Notification::assertSentTo($user, ActivateAccount::class);
    }

    public function test_forgot_password_sends_reset_for_activated_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    // --- Activation sent page ---

    public function test_activation_sent_page_renders(): void
    {
        $response = $this->get(route('activation.sent'));

        $response->assertStatus(200);
    }

    // --- Normal password reset still works for activated users ---

    public function test_password_reset_does_not_auto_login_for_activated_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            $response->assertRedirect('/login');

            return true;
        });
    }
}
