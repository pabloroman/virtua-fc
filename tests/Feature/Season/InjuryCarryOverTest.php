<?php

namespace Tests\Feature\Season;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\StatsResetProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InjuryCarryOverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();

        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
            'current_date' => '2025-05-28',
        ]);
    }

    public function test_long_term_injury_extending_into_next_season_is_preserved(): void
    {
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'name' => 'Long Recovery',
                'injury_until' => '2025-11-15',
                'injury_type' => 'ACL tear',
            ]);

        $this->runStatsResetProcessor();

        $player->refresh();
        $this->assertSame('2025-11-15', $player->injury_until?->toDateString());
        $this->assertSame('ACL tear', $player->injury_type);
    }

    public function test_short_term_injury_that_expires_before_new_season_is_also_preserved(): void
    {
        // The day-to-day CheckRecoveredPlayers listener — not this processor —
        // is responsible for clearing past-dated injuries on the next
        // GameDateAdvanced. Verify we don't preempt that contract here.
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'name' => 'Short Recovery',
                'injury_until' => '2025-06-10',
                'injury_type' => 'Sprained ankle',
            ]);

        $this->runStatsResetProcessor();

        $player->refresh();
        $this->assertSame('2025-06-10', $player->injury_until?->toDateString());
        $this->assertSame('Sprained ankle', $player->injury_type);
    }

    public function test_stats_are_still_reset_alongside_preserved_injury(): void
    {
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'name' => 'Stats Reset',
                'injury_until' => '2025-11-15',
                'injury_type' => 'ACL tear',
                'goals' => 12,
                'appearances' => 30,
                'yellow_cards' => 4,
                'fitness' => 55,
            ]);

        $this->runStatsResetProcessor();

        $player->refresh()->load('matchState');
        $matchState = $player->matchState;
        $this->assertSame(0, $matchState->goals);
        $this->assertSame(0, $matchState->appearances);
        $this->assertSame(0, $matchState->yellow_cards);
        $this->assertSame(80, $matchState->fitness);
        $this->assertSame('2025-11-15', $player->injury_until?->toDateString());
    }

    public function test_carried_over_injury_is_surfaced_in_notification_feed(): void
    {
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'name' => 'Notified Player',
                'injury_until' => '2025-11-15',
                'injury_type' => 'ACL tear',
            ]);

        $this->runStatsResetProcessor();

        $notification = GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_PLAYER_INJURED)
            ->where('metadata->player_id', $player->id)
            ->first();

        $this->assertNotNull($notification, 'Expected a TYPE_PLAYER_INJURED notification for the carried-over injury');
        $this->assertSame('ACL tear', $notification->metadata['injury_type']);
        $this->assertGreaterThanOrEqual(1, $notification->metadata['weeks_out']);
    }

    public function test_no_injury_notification_when_player_injury_already_expired_before_new_season(): void
    {
        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'name' => 'Already Recovered',
                'injury_until' => '2025-06-10',
                'injury_type' => 'Sprained ankle',
            ]);

        $this->runStatsResetProcessor();

        $injuryNotifications = GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_PLAYER_INJURED)
            ->count();

        $this->assertSame(0, $injuryNotifications);
    }

    public function test_only_user_team_players_get_carry_over_notification(): void
    {
        $otherTeam = Team::factory()->create();

        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($otherTeam)
            ->create([
                'name' => 'Rival Player',
                'injury_until' => '2025-11-15',
                'injury_type' => 'ACL tear',
            ]);

        $this->runStatsResetProcessor();

        $this->assertSame(
            0,
            GameNotification::where('game_id', $this->game->id)
                ->where('type', GameNotification::TYPE_PLAYER_INJURED)
                ->count(),
            'Rival-team injuries should not generate user-facing notifications',
        );
    }

    private function runStatsResetProcessor(): void
    {
        $data = new SeasonTransitionData(
            oldSeason: '2024',
            newSeason: '2025',
            competitionId: 'ESP1',
        );

        app(StatsResetProcessor::class)->process($this->game, $data);
    }
}
