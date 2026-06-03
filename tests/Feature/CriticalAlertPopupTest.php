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
 * Covers the critical-alert popup mechanism: PRIORITY_CRITICAL is the must-dismiss
 * popup tier, so previously-critical notifications must have been downgraded to
 * WARNING. The popup groups all pending criticals of one type (the most recent),
 * showing them together with a single dismiss/action since they route to the same
 * page; criticals of other types surface as their own group on a later load.
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

    private function makeNotification(
        string $priority,
        ?string $readAt = null,
        string $type = GameNotification::TYPE_TRANSFER_COMPLETE,
        string $title = 'Alert',
        ?string $gameDate = null,
    ): GameNotification {
        // The popup is type-agnostic — it keys on priority/type, not the type's
        // semantics — but the primary button's label IS type-driven (getActionLabel),
        // so tests that assert the label pass a concrete type. game_date drives the
        // "most recent type" grouping, so tests that exercise grouping set it.
        return GameNotification::create([
            'id' => fake()->uuid(),
            'game_id' => $this->game->id,
            'type' => $type,
            'title' => $title,
            'priority' => $priority,
            'game_date' => $gameDate,
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

    public function test_mark_critical_as_read_scopes_to_the_given_type(): void
    {
        $offer = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED);
        $advancement = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_COMPETITION_ADVANCEMENT);

        $affected = $this->service->markCriticalAsRead($this->game->id, GameNotification::TYPE_TRANSFER_OFFER_RECEIVED);

        $this->assertSame(1, $affected);
        $this->assertNotNull($offer->fresh()->read_at);
        $this->assertNull($advancement->fresh()->read_at, 'a different type must stay pending');
    }

    public function test_pending_critical_alert_group_returns_only_the_most_recent_type(): void
    {
        // Older celebratory advancement, then two newer offers of the same type,
        // plus noise (a warning and an already-read critical) that must be ignored.
        $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_COMPETITION_ADVANCEMENT, gameDate: '2024-09-10');
        $offerA = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED, gameDate: '2024-09-15');
        $offerB = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED, gameDate: '2024-09-14');
        $this->makeNotification(GameNotification::PRIORITY_WARNING, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED, gameDate: '2024-09-15');
        $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED, readAt: '2024-09-15', gameDate: '2024-09-15');

        $group = $this->service->pendingCriticalAlertGroup($this->game->id);

        $this->assertCount(2, $group);
        $this->assertEqualsCanonicalizing([$offerA->id, $offerB->id], $group->pluck('id')->all());
        $this->assertTrue($group->every(fn ($n) => $n->type === GameNotification::TYPE_TRANSFER_OFFER_RECEIVED));
    }

    public function test_pending_critical_alert_group_is_empty_when_nothing_pending(): void
    {
        $this->makeNotification(GameNotification::PRIORITY_WARNING);

        $this->assertTrue($this->service->pendingCriticalAlertGroup($this->game->id)->isEmpty());
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

    public function test_dismiss_scopes_to_the_posted_type(): void
    {
        // The popup groups by type and posts that type, so dismissing clears the
        // whole same-type group while criticals of other types stay pending.
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $offerA = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED);
        $offerB = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED);
        $advancement = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_COMPETITION_ADVANCEMENT);

        $this->actingAs($this->user)->post(
            route('game.notifications.acknowledge-critical', $this->game->id),
            ['type' => GameNotification::TYPE_TRANSFER_OFFER_RECEIVED],
        );

        $this->assertNotNull($offerA->fresh()->read_at, 'all of the posted type are cleared together');
        $this->assertNotNull($offerB->fresh()->read_at, 'all of the posted type are cleared together');
        $this->assertNull($advancement->fresh()->read_at, 'a different type should stay pending');
    }

    public function test_view_critical_route_clears_the_type_and_redirects_to_its_page(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $offerA = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED);
        $offerB = $this->makeNotification(GameNotification::PRIORITY_CRITICAL, type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED);

        $response = $this->actingAs($this->user)->post(
            route('game.notifications.view-critical', $this->game->id),
            ['type' => GameNotification::TYPE_TRANSFER_OFFER_RECEIVED],
        );

        // Transfer offers route to the outgoing transfers page; the whole group is
        // cleared so it does not re-pop on the destination.
        $response->assertRedirect(route('game.transfers.outgoing', ['gameId' => $this->game->id]));
        $this->assertNotNull($offerA->fresh()->read_at);
        $this->assertNotNull($offerB->fresh()->read_at);
    }

    public function test_action_label_is_contextual_to_the_notification_type(): void
    {
        $offer = $this->makeNotification(
            GameNotification::PRIORITY_CRITICAL,
            type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED,
        );
        $advancement = $this->makeNotification(
            GameNotification::PRIORITY_CRITICAL,
            type: GameNotification::TYPE_COMPETITION_ADVANCEMENT,
        );
        $other = $this->makeNotification(
            GameNotification::PRIORITY_CRITICAL,
            type: GameNotification::TYPE_LOAN_REQUEST_RESULT,
        );

        $this->assertSame(__('notifications.action_review_offer'), $offer->getActionLabel());
        $this->assertSame(__('notifications.action_view_competition'), $advancement->getActionLabel());
        $this->assertSame(__('notifications.action_view_details'), $other->getActionLabel());
    }

    public function test_popup_renders_only_when_a_critical_alert_is_present(): void
    {
        $heading = __('notifications.alert_heading');

        $empty = Blade::render(
            '<x-critical-alert-modal :alerts="$alerts" :game="$game" />',
            ['alerts' => collect(), 'game' => $this->game],
        );
        $this->assertStringNotContainsString($heading, $empty);

        $alert = $this->makeNotification(
            GameNotification::PRIORITY_CRITICAL,
            type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED,
        );
        $withAlert = Blade::render(
            '<x-critical-alert-modal :alerts="$alerts" :game="$game" />',
            ['alerts' => collect([$alert]), 'game' => $this->game],
        );

        $this->assertStringContainsString($heading, $withAlert);
        // Contextual primary button navigates via the type-scoped view route...
        $this->assertStringContainsString(__('notifications.action_review_offer'), $withAlert);
        $this->assertStringContainsString(
            route('game.notifications.view-critical', $this->game->id),
            $withAlert,
        );
        // ...and the quiet dismiss posts the acknowledge route scoped to this type.
        $this->assertStringContainsString(
            route('game.notifications.acknowledge-critical', $this->game->id),
            $withAlert,
        );
        $this->assertStringContainsString('name="type" value="' . $alert->type . '"', $withAlert);
        // A transfer offer is not celebratory, so it uses the danger frame.
        $this->assertStringNotContainsString(__('notifications.celebration_heading'), $withAlert);
    }

    public function test_grouped_popup_lists_all_same_type_alerts(): void
    {
        $offerA = $this->makeNotification(
            GameNotification::PRIORITY_CRITICAL,
            type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED,
            title: 'Offer for Pedri',
        );
        $offerB = $this->makeNotification(
            GameNotification::PRIORITY_CRITICAL,
            type: GameNotification::TYPE_TRANSFER_OFFER_RECEIVED,
            title: 'Offer for Gavi',
        );

        $html = Blade::render(
            '<x-critical-alert-modal :alerts="$alerts" :game="$game" />',
            ['alerts' => collect([$offerA, $offerB]), 'game' => $this->game],
        );

        // Count-aware grouped heading, both alert titles, and a single shared
        // dismiss/action pair scoped to the group's type.
        $this->assertStringContainsString(__('notifications.alerts_heading', ['count' => 2]), $html);
        $this->assertStringContainsString('Offer for Pedri', $html);
        $this->assertStringContainsString('Offer for Gavi', $html);
        $this->assertStringContainsString(__('notifications.dismiss_all'), $html);
        $this->assertStringContainsString(__('notifications.action_review_offer'), $html);
        $this->assertStringContainsString(
            route('game.notifications.view-critical', $this->game->id),
            $html,
        );
        $this->assertStringContainsString('name="type" value="' . GameNotification::TYPE_TRANSFER_OFFER_RECEIVED . '"', $html);
        // The dismiss form posts a type, never a single notification id.
        $this->assertStringNotContainsString('name="notification_id"', $html);
    }

    public function test_celebratory_critical_renders_the_celebration_frame(): void
    {
        // Positive competition events (advancement / trophy share this type) swap
        // the red "important alert" frame for the green "congratulations" one.
        $alert = $this->makeNotification(
            GameNotification::PRIORITY_CRITICAL,
            type: GameNotification::TYPE_COMPETITION_ADVANCEMENT,
        );

        $html = Blade::render(
            '<x-critical-alert-modal :alerts="$alerts" :game="$game" />',
            ['alerts' => collect([$alert]), 'game' => $this->game],
        );

        $this->assertStringContainsString(__('notifications.celebration_heading'), $html);
        $this->assertStringContainsString(__('notifications.alert_continue'), $html);
        $this->assertStringNotContainsString(__('notifications.alert_heading'), $html);
    }
}
