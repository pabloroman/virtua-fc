<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Modules\Lineup\Services\SubstitutionService;
use App\Modules\Match\Enums\MatchPhase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

/**
 * Regression for #1230: a substitution was wrongly blocked as "player sent
 * off" when the red card was pre-simulated but not yet revealed in the live
 * feed.
 *
 * The validator compared the persisted MatchEvent.minute (phase-relative base
 * minute) against the submission minute (absolute clock minute). The two
 * coordinate systems differ by the accumulated stoppage offset, so a second-
 * half red at base minute 50 (absolute 50 + first_half_stoppage) was treated
 * as already elapsed by a sub made at an earlier absolute minute.
 */
class SubstitutionRedCardMinuteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLineups;

    private SubstitutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SubstitutionService;
    }

    /**
     * Build a match where the user's team is home, with a configurable
     * first-half stoppage, a red card recorded for one starter, and a bench
     * player available to bring on.
     *
     * @return array{0: Game, 1: GameMatch, 2: GamePlayer, 3: GamePlayer}
     */
    private function makeScenario(MatchPhase $redPhase, int $redBaseMinute, int $firstHalfStoppage): array
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $game = Game::factory()->forTeam($team)->create(['current_date' => '2025-10-01']);

        $lineup = $this->createLineup($game, $team, 11, 75);

        $match = GameMatch::factory()
            ->forGame($game)
            ->between($team, $opponent)
            ->create([
                'first_half_stoppage' => $firstHalfStoppage,
                'second_half_stoppage' => 3,
                'home_lineup' => $lineup->pluck('id')->all(),
            ]);

        $sentOff = $lineup[7]; // Defensive Midfield starter

        MatchEvent::create([
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'team_id' => $team->id,
            'game_player_id' => $sentOff->id,
            'event_type' => 'red_card',
            'phase' => $redPhase,
            'minute' => $redBaseMinute,
            'stoppage_minute' => null,
        ]);

        $bench = GamePlayer::factory()
            ->forGame($game)
            ->forTeam($team)
            ->create([
                'position' => 'Defensive Midfield',
                'number' => 99,
                'injury_until' => null,
            ]);

        return [$game, $match, $sentOff, $bench];
    }

    private function sub(GamePlayer $out, GamePlayer $in): array
    {
        return [['playerOutId' => $out->id, 'playerInId' => $in->id]];
    }

    public function test_second_half_red_is_not_counted_before_its_absolute_minute(): void
    {
        // Red at base minute 50 + 3' first-half stoppage = absolute minute 53.
        [$game, $match, $sentOff, $bench] = $this->makeScenario(MatchPhase::SECOND_HALF, 50, 3);

        // Sub at absolute minute 51 — the red (absolute 53) has not been
        // revealed yet, so it must NOT block the substitution.
        $this->service->validateBatchSubstitution(
            $match,
            $game,
            $this->sub($sentOff, $bench),
            51,
            [],
        );

        // No exception thrown == the sub is allowed.
        $this->addToAssertionCount(1);
    }

    public function test_second_half_red_blocks_at_or_after_its_absolute_minute(): void
    {
        [$game, $match, $sentOff, $bench] = $this->makeScenario(MatchPhase::SECOND_HALF, 50, 3);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('game.sub_error_player_sent_off');

        // Sub at absolute minute 55 (>= 53) — the red is now elapsed, block it.
        $this->service->validateBatchSubstitution(
            $match,
            $game,
            $this->sub($sentOff, $bench),
            55,
            [],
        );
    }

    public function test_first_half_red_still_blocks_correctly(): void
    {
        // First-half phase has no stoppage offset, so base == absolute.
        [$game, $match, $sentOff, $bench] = $this->makeScenario(MatchPhase::FIRST_HALF, 30, 3);

        // Before the red: allowed.
        $this->service->validateBatchSubstitution(
            $match,
            $game,
            $this->sub($sentOff, $bench),
            29,
            [],
        );
        $this->addToAssertionCount(1);

        // At/after the red: blocked.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('game.sub_error_player_sent_off');

        $this->service->validateBatchSubstitution(
            $match,
            $game,
            $this->sub($sentOff, $bench),
            31,
            [],
        );
    }
}
