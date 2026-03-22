<?php

namespace Tests\Feature\Auth;

use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    // --- Open registration ---

    public function test_registration_screen_can_be_rendered(): void
    {
        config()->set('beta.enabled', false);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Notification::fake();
        config()->set('beta.enabled', false);

        $response = $this->post(route('register.tournament-mode'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('activation.sent'));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'has_career_access' => false,
            'has_tournament_access' => true,
        ]);
    }

    // --- Beta registration (BETA_MODE=true) ---

    public function test_beta_registration_requires_invite_code(): void
    {
        config()->set('beta.enabled', true);

        $response = $this->get('/register');

        $response->assertRedirect(route('login'));
    }

    public function test_beta_registration_rejects_invalid_invite(): void
    {
        config()->set('beta.enabled', true);

        $response = $this->get('/register?invite=INVALID');

        $response->assertRedirect(route('login'));
    }

    public function test_beta_registration_screen_rendered_with_valid_invite(): void
    {
        config()->set('beta.enabled', true);

        InviteCode::create([
            'code' => 'VALID-CODE',
            'email' => 'beta@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->get('/register?invite=VALID-CODE');

        $response->assertStatus(200);
    }

    public function test_beta_users_can_register_with_valid_invite(): void
    {
        Notification::fake();
        config()->set('beta.enabled', true);

        InviteCode::create([
            'code' => 'BETA-INVITE',
            'email' => 'beta@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->post(route('register.career-mode', ['invite' => 'BETA-INVITE']), [
            'name' => 'Beta Tester',
            'email' => 'beta@example.com',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
            'invite_code' => 'BETA-INVITE',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('invite_codes', [
            'code' => 'BETA-INVITE',
            'times_used' => 1,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'beta@example.com',
            'has_career_access' => true,
            'has_tournament_access' => false,
        ]);
    }

    public function test_beta_registration_fails_with_wrong_email(): void
    {
        Notification::fake();
        config()->set('beta.enabled', true);

        InviteCode::create([
            'code' => 'EMAIL-LOCKED',
            'email' => 'specific@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->post(route('register.career-mode', ['invite' => 'EMAIL-LOCKED']), [
            'name' => 'Wrong Email',
            'email' => 'different@example.com',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
            'invite_code' => 'EMAIL-LOCKED',
        ]);

        $response->assertSessionHasErrors('invite_code');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', [
            'email' => 'different@example.com',
        ]);
        $this->assertDatabaseHas('invite_codes', [
            'code' => 'EMAIL-LOCKED',
            'times_used' => 0,
        ]);
    }

    // --- Career access via invite code (open registration) ---

    public function test_registration_without_invite_does_not_grant_career_access(): void
    {
        Notification::fake();
        config()->set('beta.enabled', false);

        $response = $this->post(route('register.tournament-mode'), [
            'name' => 'No Invite',
            'email' => 'noinvite@example.com',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('activation.sent'));
        $this->assertDatabaseHas('users', [
            'email' => 'noinvite@example.com',
            'has_career_access' => false,
            'has_tournament_access' => true,
        ]);
    }

    public function test_registration_with_valid_invite_grants_career_access(): void
    {
        Notification::fake();
        config()->set('beta.enabled', false);

        InviteCode::create([
            'code' => 'CAREER-INVITE',
            'email' => 'beta@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->post(route('register.career-mode'), [
            'name' => 'Beta Tester',
            'email' => 'beta@example.com',
            'password' => 'my-new-password',
            'password_confirmation' => 'my-new-password',
            'invite_code' => 'CAREER-INVITE',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'beta@example.com',
            'has_career_access' => true,
            'has_tournament_access' => false,
        ]);
        $this->assertDatabaseHas('invite_codes', [
            'code' => 'CAREER-INVITE',
            'times_used' => 1,
        ]);
    }
}
