<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_routes_are_disabled(): void
    {
        $this->get('/forgot-password')->assertStatus(404);
        $this->post('/forgot-password', ['email' => 'test@example.com'])->assertStatus(404);
        $this->get('/reset-password/fake-token')->assertStatus(404);
        $this->post('/reset-password', [])->assertStatus(404);
    }
}
