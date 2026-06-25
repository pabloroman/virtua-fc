<?php

namespace Tests\Feature\Notification;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Notification\Listeners\SendMatchNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The user's own match notifies injuries via SendMatchNotifications (the deferred
 * path), separately from MatchResultProcessor which actually applies the injury.
 * MatchResultProcessor skips "cosmetic" injuries that overlap no fixtures, leaving
 * injury_until null; the notification must follow suit so the user never sees a
 * phantom injury alert for an absence that never happens.
 */
class InjuryNotificationGuardTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $team;
    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->team = Team::factory()->create();

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'current_date' => '2025-08-17',
        ]);

        $this->match = GameMatch::factory()
            ->forGame($this->game)
            ->forCompetition(Competition::find('ESP1'))
            ->scheduledOn('2025-07-22')
            ->played(1, 0)
            ->create(['home_team_id' => $this->team->id]);
    }

    public function test_cosmetic_injury_with_no_missed_matches_is_not_notified(): void
    {
        // Injured during the match but never sidelined for a fixture — injury_until
        // stays null because MatchResultProcessor's guard skipped applying it.
        $player = $this->makePlayerWithInjuryEvent(injuryUntil: null);

        $this->dispatchFinalized();

        $this->assertDatabaseMissing('game_notifications', [
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_PLAYER_INJURED,
        ]);
    }

    public function test_real_injury_that_sidelines_the_player_is_notified(): void
    {
        $player = $this->makePlayerWithInjuryEvent(injuryUntil: '2025-08-05');

        $this->dispatchFinalized();

        $notification = GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_PLAYER_INJURED)
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame($player->id, $notification->metadata['player_id']);
    }

    private function makePlayerWithInjuryEvent(?string $injuryUntil): GamePlayer
    {
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'name' => 'Kylian Mbappé',
                'injury_until' => $injuryUntil,
                'injury_type' => $injuryUntil ? 'Muscle strain' : null,
            ]);

        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $player->id,
            'team_id' => $this->team->id,
            'minute' => 37,
            'event_type' => MatchEvent::TYPE_INJURY,
            'metadata' => ['injury_type' => 'Muscle strain', 'weeks_out' => 2],
        ]);

        return $player;
    }

    private function dispatchFinalized(): void
    {
        $event = new MatchFinalized($this->match->fresh(), $this->game->fresh(), Competition::find('ESP1'));

        app(SendMatchNotifications::class)->handle($event);
    }
}
