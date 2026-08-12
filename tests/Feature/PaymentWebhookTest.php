<?php

namespace Tests\Feature;

use App\Models\InviteCode;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'ko-fi-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_webhook_is_rejected_when_no_secret_is_configured(): void
    {
        config(['beta.webhook_secret' => null]);

        $entry = $this->waitlisted('supporter@example.com');

        // Omitting the token entirely is the case an unconfigured secret used
        // to let through: null !== null is false.
        $this->postJson('/api/webhooks/ko-fi', [
            'data' => json_encode(['supporter_email' => $entry->email]),
        ])->assertForbidden();

        $this->assertDatabaseCount('invite_codes', 0);
    }

    public function test_webhook_is_rejected_with_a_wrong_token(): void
    {
        config(['beta.webhook_secret' => self::SECRET]);

        $entry = $this->waitlisted('supporter@example.com');

        $this->postJson('/api/webhooks/ko-fi', [
            'verification_token' => 'not-the-secret',
            'data' => json_encode(['supporter_email' => $entry->email]),
        ])->assertForbidden();

        $this->assertDatabaseCount('invite_codes', 0);
    }

    public function test_webhook_invites_a_waitlisted_supporter_with_the_correct_token(): void
    {
        config(['beta.webhook_secret' => self::SECRET]);

        $entry = $this->waitlisted('supporter@example.com');

        $this->postJson('/api/webhooks/ko-fi', [
            'verification_token' => self::SECRET,
            'data' => json_encode(['supporter_email' => $entry->email]),
        ])->assertOk()->assertJson(['status' => 'ok']);

        $this->assertTrue(InviteCode::where('email', $entry->email)->exists());
    }

    private function waitlisted(string $email): WaitlistEntry
    {
        return WaitlistEntry::create([
            'name' => 'Test Supporter',
            'email' => $email,
            'wants_career' => true,
            'wants_tournament' => false,
        ]);
    }
}
