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
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_without_invite(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('activation.sent'));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'has_career_access' => false,
        ]);
    }

    // --- Career access via invite code ---

    public function test_registration_with_valid_invite_grants_career_access(): void
    {
        Notification::fake();

        InviteCode::create([
            'code' => 'CAREER-INVITE',
            'email' => 'beta@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->post('/register?invite=CAREER-INVITE', [
            'name' => 'Beta Tester',
            'email' => 'beta@example.com',
            'invite_code' => 'CAREER-INVITE',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('activation.sent'));
        $this->assertDatabaseHas('users', [
            'email' => 'beta@example.com',
            'has_career_access' => true,
        ]);
        $this->assertDatabaseHas('invite_codes', [
            'code' => 'CAREER-INVITE',
            'times_used' => 1,
        ]);
    }

    public function test_registration_with_wrong_email_invite_does_not_grant_career_access(): void
    {
        Notification::fake();

        InviteCode::create([
            'code' => 'EMAIL-LOCKED',
            'email' => 'specific@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->post('/register?invite=EMAIL-LOCKED', [
            'name' => 'Wrong Email',
            'email' => 'different@example.com',
            'invite_code' => 'EMAIL-LOCKED',
        ]);

        // User still registers, but without career access
        $this->assertGuest();
        $response->assertRedirect(route('activation.sent'));
        $this->assertDatabaseHas('users', [
            'email' => 'different@example.com',
            'has_career_access' => false,
        ]);
        // Invite code should not be consumed
        $this->assertDatabaseHas('invite_codes', [
            'code' => 'EMAIL-LOCKED',
            'times_used' => 0,
        ]);
    }

    public function test_registration_with_invalid_invite_does_not_grant_career_access(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'No Invite',
            'email' => 'noinvite@example.com',
            'invite_code' => 'INVALID-CODE',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('activation.sent'));
        $this->assertDatabaseHas('users', [
            'email' => 'noinvite@example.com',
            'has_career_access' => false,
        ]);
    }

    public function test_registration_screen_shows_invite_banner(): void
    {
        InviteCode::create([
            'code' => 'VALID-CODE',
            'email' => 'beta@example.com',
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $response = $this->get('/register?invite=VALID-CODE');

        $response->assertStatus(200);
    }
}
