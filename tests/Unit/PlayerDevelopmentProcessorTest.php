<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\Team;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\PlayerDevelopmentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerDevelopmentProcessorTest extends TestCase
{
    use RefreshDatabase;

    private const SEASON_END = '2025-06-30';

    private PlayerDevelopmentProcessor $processor;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = app(PlayerDevelopmentProcessor::class);

        $this->game = Game::factory()->atDate(self::SEASON_END)->create();
    }

    public function test_young_player_with_appearances_grows_and_gets_gap_bonus(): void
    {
        // 18yo, 20 apps, big gap to potential → +1 gap bonus on top of curve growth.
        $player = $this->makePlayer(age: 18, overall: 60, potential: 85, appearances: 20);

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        // Curve growth at age 18: +2. calculateChange(2, 20) = round(2 * 20/25) = 2.
        // Gap bonus (+1) applies: pot - overall = 85 - 60 = 25 >= 15, age < 23.
        $this->assertSame(63, $player->overall_score);
    }

    public function test_young_player_without_enough_appearances_grows_at_training_rate(): void
    {
        // 18yo, 5 apps (below MIN_APPEARANCES_FOR_GROWTH=10).
        // Curve at 18: +2 → training-only halves to +1.
        // Gap bonus (+1) still applies when delta > 0 and pot - overall >= 15, age < 23.
        $player = $this->makePlayer(age: 18, overall: 60, potential: 85, appearances: 5);

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        $this->assertSame(62, $player->overall_score);
    }

    public function test_inactive_veteran_declines_at_full_rate(): void
    {
        // 33yo with only 2 apps: full decline (not halved).
        $player = $this->makePlayer(age: 33, overall: 78, potential: 85, appearances: 2);

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        // Age 33 curve: -4 (full decline since apps < 10).
        $this->assertSame(74, $player->overall_score);
    }

    public function test_active_veteran_declines_at_half_rate(): void
    {
        // 33yo with 25 apps: decline halved.
        $player = $this->makePlayer(age: 33, overall: 78, potential: 85, appearances: 25);

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        // round(-4 * 0.5) = -2.
        $this->assertSame(76, $player->overall_score);
    }

    public function test_growth_is_capped_at_potential(): void
    {
        // 16yo already near potential: growth clamps to pot ceiling.
        $player = $this->makePlayer(age: 16, overall: 74, potential: 75, appearances: 25);

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        // Curve at 16: +3 with full apps. Gap = 75 - 74 = 1, no gap bonus.
        // +3 would go to 77; capped at pot=75.
        $this->assertSame(75, $player->overall_score);
    }

    public function test_pool_player_without_match_state_decays_as_inactive(): void
    {
        // No matchState row → apps = 0 → behaves as inactive.
        $player = $this->makePoolPlayer(age: 33, overall: 78, potential: 85);

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        $this->assertSame(74, $player->overall_score);
    }

    public function test_market_value_and_tier_are_recomputed(): void
    {
        $player = $this->makePlayer(
            age: 18,
            overall: 60,
            potential: 85,
            appearances: 20,
            marketValueCents: 100_000_00, // €100K (tier 1) — will get revalued up
        );

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        // After development: overall=63 (anchored at €2M base in
        // PlayerValuationService::ABILITY_VALUE_ANCHORS).
        // Age 18 multiplier 1.30, trend multiplier 1.10 (change=+3, young).
        // 200M * 1.30 * 1.10 = 286M cents.
        $expectedMV = 286_000_000;

        $this->assertSame($expectedMV, $player->market_value_cents);
        $this->assertSame(PlayerTierService::tierFromMarketValue($expectedMV), $player->tier);
    }

    public function test_skips_rows_with_null_overall(): void
    {
        // Rows with NULL overall_score are skipped — there's no longer a
        // control-plane fallback to draw from.
        $team = Team::factory()->create();
        $gamePlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->age(25, self::SEASON_END)
            ->create([
                'overall_score' => null,
                'potential' => 80,
                'season_appearances' => 25,
            ]);

        $this->processor->process($this->game, $this->transitionData());

        $gamePlayer->refresh();
        $this->assertNull($gamePlayer->overall_score);
    }

    public function test_other_games_are_untouched(): void
    {
        $otherGame = Game::factory()->atDate(self::SEASON_END)->create();
        $otherPlayer = $this->makePlayer(
            age: 18,
            overall: 60,
            potential: 85,
            appearances: 25,
            game: $otherGame,
        );

        $this->processor->process($this->game, $this->transitionData());

        $this->assertSame(60, $otherPlayer->fresh()->overall_score);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function transitionData(): SeasonTransitionData
    {
        return new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->game->competition_id,
        );
    }

    private function makePlayer(
        int $age,
        int $overall,
        int $potential,
        int $appearances,
        ?int $marketValueCents = null,
        ?Game $game = null,
    ): GamePlayer {
        $team = Team::factory()->create();

        $attributes = [
            'overall_score' => $overall,
            'potential' => $potential,
            'season_appearances' => $appearances,
        ];

        if ($marketValueCents !== null) {
            $attributes['market_value_cents'] = $marketValueCents;
        }

        return GamePlayer::factory()
            ->forGame($game ?? $this->game)
            ->forTeam($team)
            ->age($age, self::SEASON_END)
            ->create($attributes);
    }

    private function makePoolPlayer(int $age, int $overall, int $potential): GamePlayer
    {
        $team = Team::factory()->create();

        return GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->pool()
            ->age($age, self::SEASON_END)
            ->create([
                'overall_score' => $overall,
                'potential' => $potential,
            ]);
    }
}
