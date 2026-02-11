<?php

namespace Tests\Feature\Auth;

use App\Models\InviteCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    // --- Open registration (BETA_MODE=false) ---

    public function test_registration_screen_can_be_rendered(): void
    {
        config()->set('beta.enabled', false);

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        config()->set('beta.enabled', false);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
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
        config()->set('beta.enabled', true);

        InviteCode::create([
            'code' => 'BETA-INVITE',
            'email' => 'beta@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->post('/register?invite=BETA-INVITE', [
            'name' => 'Beta Tester',
            'email' => 'beta@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite_code' => 'BETA-INVITE',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('invite_codes', [
            'code' => 'BETA-INVITE',
            'times_used' => 1,
        ]);
    }

    public function test_beta_registration_fails_with_wrong_email(): void
    {
        config()->set('beta.enabled', true);

        InviteCode::create([
            'code' => 'EMAIL-LOCKED',
            'email' => 'specific@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->post('/register?invite=EMAIL-LOCKED', [
            'name' => 'Wrong Email',
            'email' => 'different@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite_code' => 'EMAIL-LOCKED',
        ]);

        $this->assertGuest();
    }
}
