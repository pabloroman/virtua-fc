<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameNotification;
use App\Models\Team;
use App\Models\User;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Covers the critical-alert popup mechanism: PRIORITY_CRITICAL is now reserved
 * as the must-dismiss popup tier, so previously-critical notifications must have
 * been downgraded to WARNING, and the popup/acknowledge plumbing must target
 * only unread CRITICAL notifications.
 */
class CriticalAlertPopupTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;
    private User $user;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NotificationService::class);
        $this->user = User::factory()->create();
        $team = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'season' => '2024',
            'current_date' => '2024-09-15',
        ]);
    }

    private function makeNotification(string $priority, ?string $readAt = null): GameNotification
    {
        // The popup is type-agnostic — it keys on priority, not type — so any
        // existing type works here. Phase 3 adds the first real CRITICAL producer.
        return GameNotification::create([
            'id' => fake()->uuid(),
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_TRANSFER_COMPLETE,
            'title' => 'Alert',
            'priority' => $priority,
            'read_at' => $readAt,
        ]);
    }

    public function test_previously_critical_notifications_are_now_warnings(): void
    {
        // A plain former-critical and both former-critical conditional branches.
        $forfeit = $this->service->notifyMatchForfeit($this->game);
        $sacked = $this->service->notifyJobOfferReceived($this->game, 1, fired: true);

        $this->assertSame(GameNotification::PRIORITY_WARNING, $forfeit->priority);
        $this->assertSame(GameNotification::PRIORITY_WARNING, $sacked->priority);

        // Nothing in the suite should now emit CRITICAL except deliberate popups.
        $this->assertFalse($forfeit->isCritical());
        $this->assertFalse($sacked->isCritical());
    }

    public function test_mark_critical_as_read_scopes_to_unread_critical_only(): void
    {
        $unreadCritical = $this->makeNotification(GameNotification::PRIORITY_CRITICAL);
        $warning = $this->makeNotification(GameNotification::PRIORITY_WARNING);
        $alreadyReadCritical = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, readAt: '2024-09-10');
        $readAtBefore = $alreadyReadCritical->read_at;

        $affected = $this->service->markCriticalAsRead($this->game->id);

        $this->assertSame(1, $affected);
        $this->assertNotNull($unreadCritical->fresh()->read_at);
        $this->assertNull($warning->fresh()->read_at, 'warnings must not be acknowledged by the critical popup');
        $this->assertEquals($readAtBefore, $alreadyReadCritical->fresh()->read_at, 'already-read criticals untouched');
    }

    public function test_acknowledge_route_marks_critical_notifications_read(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $critical = $this->makeNotification(GameNotification::PRIORITY_CRITICAL);

        $response = $this->actingAs($this->user)
            ->post(route('game.notifications.acknowledge-critical', $this->game->id));

        $response->assertRedirect();
        $this->assertNotNull($critical->fresh()->read_at);
    }

    public function test_popup_component_renders_only_when_critical_alerts_are_present(): void
    {
        $heading = __('notifications.alert_heading');

        $empty = Blade::render(
            '<x-critical-alert-modal :alerts="$alerts" :game="$game" />',
            ['alerts' => collect(), 'game' => $this->game],
        );
        $this->assertStringNotContainsString($heading, $empty);

        $withAlert = Blade::render(
            '<x-critical-alert-modal :alerts="$alerts" :game="$game" />',
            ['alerts' => collect([$this->makeNotification(GameNotification::PRIORITY_CRITICAL)]), 'game' => $this->game],
        );
        $this->assertStringContainsString($heading, $withAlert);
        $this->assertStringContainsString(
            route('game.notifications.acknowledge-critical', $this->game->id),
            $withAlert,
        );
    }
}
