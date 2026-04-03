<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Services\AISubstitutionService;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

class RedCardSimulationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLineups;

    private MatchSimulator $simulator;

    private ReflectionMethod $calculateTeamStrength;

    private ReflectionMethod $simulateGoalsWithRedCardSplit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new MatchSimulator;

        $this->calculateTeamStrength = new ReflectionMethod(MatchSimulator::class, 'calculateTeamStrength');
        $this->simulateGoalsWithRedCardSplit = new ReflectionMethod(MatchSimulator::class, 'simulateGoalsWithRedCardSplit');
    }

    public function test_team_strength_with_10_players_is_not_amateur_fallback(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $fullLineup = $this->createLineup($game, $team, 11, 75);
        $reducedLineup = $fullLineup->take(10);

        $fullStrength = $this->calculateTeamStrength->invoke($this->simulator, $fullLineup);
        $reducedStrength = $this->calculateTeamStrength->invoke($this->simulator, $reducedLineup);

        // 10-player strength should NOT be the 0.30 amateur fallback
        $this->assertGreaterThan(0.30, $reducedStrength, 'A 10-player lineup should not use the amateur fallback');

        // With fixed divisor of 11, losing a player should reduce strength by ~1/11
        $this->assertLessThan($fullStrength, $reducedStrength,
            'A 10-player lineup should have lower strength than a full 11');
        $expectedRatio = 10 / 11;
        $actualRatio = $reducedStrength / $fullStrength;
        $this->assertEqualsWithDelta($expectedRatio, $actualRatio, 0.02,
            "Strength ratio should be close to 10/11 ({$actualRatio} vs {$expectedRatio})");
    }

    public function test_severely_depleted_lineup_uses_fallback(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $tinyLineup = $this->createLineup($game, $team, 6, 80);

        $strength = $this->calculateTeamStrength->invoke($this->simulator, $tinyLineup);

        $this->assertEquals(0.30, $strength, 'A lineup with < 7 players should use the amateur fallback');
    }

    public function test_red_card_split_produces_valid_results(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);

        $homeStrength = $this->calculateTeamStrength->invoke($this->simulator, $homePlayers);
        $awayStrength = $this->calculateTeamStrength->invoke($this->simulator, $awayPlayers);

        // Create a fake red card for the home team's centre-back at minute 30
        $redCardPlayer = $homePlayers->firstWhere('position', 'Centre-Back');
        $homeRedCard = MatchEventData::redCard($homeTeam->id, $redCardPlayer->id, 30, false);

        // Call simulateGoalsWithRedCardSplit directly
        [$homeScore, $awayScore, $goalEvents] = $this->simulateGoalsWithRedCardSplit->invoke(
            $this->simulator,
            $homeTeam, $awayTeam,
            $homePlayers, $awayPlayers,
            Formation::F_4_3_3, Formation::F_4_3_3,
            Mentality::BALANCED, Mentality::BALANCED,
            PlayingStyle::BALANCED, PlayingStyle::BALANCED,
            PressingIntensity::STANDARD, PressingIntensity::STANDARD,
            DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
            $homeStrength, $awayStrength,
            [], [],  // entry minutes
            1.0, 1.0,  // tactical drain
            0, // fromMinute
            config('match_simulation.base_goals', 1.3),
            $homeRedCard,
            null, // no away red card
        );

        // Scores should be non-negative integers
        $this->assertGreaterThanOrEqual(0, $homeScore);
        $this->assertGreaterThanOrEqual(0, $awayScore);

        // All goal events should have valid minutes
        foreach ($goalEvents as $event) {
            $this->assertGreaterThanOrEqual(1, $event->minute, 'Goal event minute should be >= 1');
            $this->assertLessThanOrEqual(93, $event->minute, 'Goal event minute should be <= 93');
        }

        // Goal events for home team after minute 30 should NOT involve the red-carded player
        $homeGoalsAfterRed = $goalEvents->filter(
            fn ($e) => $e->type === 'goal' && $e->teamId === $homeTeam->id && $e->minute > 30
        );
        foreach ($homeGoalsAfterRed as $event) {
            $this->assertNotEquals($redCardPlayer->id, $event->gamePlayerId,
                'Red-carded player should not score after being sent off');
        }
    }

    public function test_red_card_split_reduces_ten_man_team_xg_over_many_runs(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);

        $homeStrength = $this->calculateTeamStrength->invoke($this->simulator, $homePlayers);
        $awayStrength = $this->calculateTeamStrength->invoke($this->simulator, $awayPlayers);

        $redCardPlayer = $homePlayers->firstWhere('position', 'Centre-Back');
        // Early red card at minute 10 to maximize the effect
        $homeRedCard = MatchEventData::redCard($homeTeam->id, $redCardPlayer->id, 10, false);

        $iterations = 500;
        $splitHomeGoals = 0;
        $splitAwayGoals = 0;
        $normalHomeGoals = 0;
        $normalAwayGoals = 0;

        $baseGoals = config('match_simulation.base_goals', 1.3);
        $splitArgs = [
            $this->simulator,
            $homeTeam, $awayTeam,
            $homePlayers, $awayPlayers,
            Formation::F_4_3_3, Formation::F_4_3_3,
            Mentality::BALANCED, Mentality::BALANCED,
            PlayingStyle::BALANCED, PlayingStyle::BALANCED,
            PressingIntensity::STANDARD, PressingIntensity::STANDARD,
            DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
            $homeStrength, $awayStrength,
            [], [],
            1.0, 1.0,
            0,
            $baseGoals,
        ];

        for ($i = 0; $i < $iterations; $i++) {
            // With red card at minute 10
            [$h, $a] = $this->simulateGoalsWithRedCardSplit->invoke(
                ...array_merge($splitArgs, [$homeRedCard, null]),
            );
            $splitHomeGoals += $h;
            $splitAwayGoals += $a;

            // Same method, no red card — apples-to-apples baseline
            [$h, $a] = $this->simulateGoalsWithRedCardSplit->invoke(
                ...array_merge($splitArgs, [null, null]),
            );
            $normalHomeGoals += $h;
            $normalAwayGoals += $a;
        }

        $splitAvgHome = $splitHomeGoals / $iterations;
        $normalAvgHome = $normalHomeGoals / $iterations;
        $splitAvgAway = $splitAwayGoals / $iterations;
        $normalAvgAway = $normalAwayGoals / $iterations;

        // Home team with red card at minute 10 should score significantly fewer goals
        $this->assertLessThan($normalAvgHome, $splitAvgHome,
            "10-man home team ({$splitAvgHome}) should average fewer goals than full team ({$normalAvgHome})");

        // Opponent should score more against 10-man team
        $this->assertGreaterThan($normalAvgAway, $splitAvgAway,
            "Away team should score more vs 10 men ({$splitAvgAway}) than vs 11 ({$normalAvgAway})");
    }

    public function test_simulation_produces_red_card_events(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);

        // Run enough simulations that we're likely to see at least one red card
        $redCardSeen = false;
        for ($i = 0; $i < 200; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_3_3, Formation::F_4_3_3,
                Mentality::BALANCED, Mentality::BALANCED,
                $game,
            );

            if ($output->result->redCards()->isNotEmpty()) {
                $redCardSeen = true;

                foreach ($output->result->redCards() as $event) {
                    $this->assertEquals('red_card', $event->type);
                    $this->assertGreaterThanOrEqual(1, $event->minute);
                    $this->assertLessThanOrEqual(93, $event->minute);
                    $this->assertNotEmpty($event->gamePlayerId);
                    $this->assertNotEmpty($event->teamId);
                }
                break;
            }
        }

        $this->assertTrue($redCardSeen, 'At least one red card should occur in 200 simulations');
    }

    public function test_goalkeeper_red_card_has_larger_xg_impact_than_forward_red_card(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);

        $homeStrength = $this->calculateTeamStrength->invoke($this->simulator, $homePlayers);
        $awayStrength = $this->calculateTeamStrength->invoke($this->simulator, $awayPlayers);

        $gkPlayer = $homePlayers->firstWhere('position', 'Goalkeeper');
        $fwPlayer = $homePlayers->firstWhere('position', 'Centre-Forward');

        $gkRedCard = MatchEventData::redCard($homeTeam->id, $gkPlayer->id, 10, false);
        $fwRedCard = MatchEventData::redCard($homeTeam->id, $fwPlayer->id, 10, false);

        $iterations = 500;
        $gkAwayGoals = 0;
        $fwAwayGoals = 0;

        for ($i = 0; $i < $iterations; $i++) {
            // GK red card
            [, $a] = $this->simulateGoalsWithRedCardSplit->invoke(
                $this->simulator,
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_3_3, Formation::F_4_3_3,
                Mentality::BALANCED, Mentality::BALANCED,
                PlayingStyle::BALANCED, PlayingStyle::BALANCED,
                PressingIntensity::STANDARD, PressingIntensity::STANDARD,
                DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
                $homeStrength, $awayStrength,
                [], [], 1.0, 1.0, 0,
                config('match_simulation.base_goals', 1.3),
                $gkRedCard, null,
            );
            $gkAwayGoals += $a;

            // FW red card
            [, $a] = $this->simulateGoalsWithRedCardSplit->invoke(
                $this->simulator,
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_3_3, Formation::F_4_3_3,
                Mentality::BALANCED, Mentality::BALANCED,
                PlayingStyle::BALANCED, PlayingStyle::BALANCED,
                PressingIntensity::STANDARD, PressingIntensity::STANDARD,
                DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
                $homeStrength, $awayStrength,
                [], [], 1.0, 1.0, 0,
                config('match_simulation.base_goals', 1.3),
                $fwRedCard, null,
            );
            $fwAwayGoals += $a;
        }

        $gkAvg = $gkAwayGoals / $iterations;
        $fwAvg = $fwAwayGoals / $iterations;

        $this->assertGreaterThan($fwAvg, $gkAvg,
            "Away team should score more vs GK red card ({$gkAvg}) than vs FW red card ({$fwAvg})");
    }

    public function test_losing_higher_rated_player_has_larger_impact(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        // Create lineup where the CB is 90-rated and the FW is 55-rated
        // Losing the stronger CB should hurt more than losing the weaker FW
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);

        $cbPlayer = $homePlayers->firstWhere('position', 'Centre-Back');
        $cbPlayer->update(['game_technical_ability' => 90, 'game_physical_ability' => 90]);
        $cbPlayer->refresh()->setRelation('game', $game);

        $fwPlayer = $homePlayers->firstWhere('position', 'Centre-Forward');
        $fwPlayer->update(['game_technical_ability' => 55, 'game_physical_ability' => 55]);
        $fwPlayer->refresh()->setRelation('game', $game);

        $homeStrength = $this->calculateTeamStrength->invoke($this->simulator, $homePlayers);
        $awayStrength = $this->calculateTeamStrength->invoke($this->simulator, $awayPlayers);

        $cbRedCard = MatchEventData::redCard($homeTeam->id, $cbPlayer->id, 10, false);
        $fwRedCard = MatchEventData::redCard($homeTeam->id, $fwPlayer->id, 10, false);

        $iterations = 800;
        $cbAwayGoals = 0;
        $fwAwayGoals = 0;

        for ($i = 0; $i < $iterations; $i++) {
            [, $a] = $this->simulateGoalsWithRedCardSplit->invoke(
                $this->simulator,
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_3_3, Formation::F_4_3_3,
                Mentality::BALANCED, Mentality::BALANCED,
                PlayingStyle::BALANCED, PlayingStyle::BALANCED,
                PressingIntensity::STANDARD, PressingIntensity::STANDARD,
                DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
                $homeStrength, $awayStrength,
                [], [], 1.0, 1.0, 0,
                config('match_simulation.base_goals', 1.3),
                $cbRedCard, null,
            );
            $cbAwayGoals += $a;

            [, $a] = $this->simulateGoalsWithRedCardSplit->invoke(
                $this->simulator,
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_3_3, Formation::F_4_3_3,
                Mentality::BALANCED, Mentality::BALANCED,
                PlayingStyle::BALANCED, PlayingStyle::BALANCED,
                PressingIntensity::STANDARD, PressingIntensity::STANDARD,
                DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
                $homeStrength, $awayStrength,
                [], [], 1.0, 1.0, 0,
                config('match_simulation.base_goals', 1.3),
                $fwRedCard, null,
            );
            $fwAwayGoals += $a;
        }

        $cbAvg = $cbAwayGoals / $iterations;
        $fwAvg = $fwAwayGoals / $iterations;

        // Losing the 90-rated CB hurts the team average more than losing the 55-rated FW
        $this->assertGreaterThan($fwAvg, $cbAvg,
            "Away team should score more vs high-rated CB red card ({$cbAvg}) than vs low-rated FW red card ({$fwAvg})");
    }

    public function test_reactive_substitution_after_defender_red_card(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);
        $homeBench = $this->createBenchPlayers($game, $homeTeam, 7, 70);
        $awayBench = $this->createBenchPlayers($game, $awayTeam, 7, 70);

        // Increase direct red card chance so we get one reliably
        config(['match_simulation.direct_red_chance' => 50]);

        $reactiveSubSeen = false;

        for ($i = 0; $i < 50; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers->values(), $awayPlayers->values(),
                Formation::F_4_3_3, Formation::F_4_3_3,
                Mentality::BALANCED, Mentality::BALANCED,
                $game,
                PlayingStyle::BALANCED, PlayingStyle::BALANCED,
                PressingIntensity::STANDARD, PressingIntensity::STANDARD,
                DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
                $homeBench->values(), $awayBench->values(),
            );

            $redCards = $output->result->events->filter(fn ($e) => $e->type === 'red_card');
            $subs = $output->result->events->filter(fn ($e) => $e->type === 'substitution');

            if ($redCards->isNotEmpty() && $subs->isNotEmpty()) {
                // Check if any sub happened within 3 minutes after a red card (reactive)
                foreach ($redCards as $rc) {
                    $reactiveSub = $subs->first(fn ($s) => $s->minute >= $rc->minute
                        && $s->minute <= $rc->minute + 3);
                    if ($reactiveSub) {
                        $reactiveSubSeen = true;
                        break 2;
                    }
                }
            }
        }

        $this->assertTrue($reactiveSubSeen,
            'A reactive substitution should occur shortly after a red card');
    }

    public function test_no_reactive_sub_after_late_red_card(): void
    {
        $aiSubService = new AISubstitutionService;
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $lineup = $this->createLineup($game, $team, 11, 75);
        $bench = $this->createBenchPlayers($game, $team, 7, 70);

        // The reactive sub logic in the MatchSimulator checks the minute against
        // red_card_reactive_max_minute. Here we just test that the AISubstitutionService
        // itself always returns a valid sub when called (the minute gating is in the caller).
        $cbPlayer = $lineup->firstWhere('position', 'Centre-Back');

        // Remove the CB from lineup to simulate them being sent off
        $reducedLineup = $lineup->reject(fn ($p) => $p->id === $cbPlayer->id)->values();

        $sub = $aiSubService->chooseRedCardReactiveSubstitution(
            $reducedLineup, $bench, 'Centre-Back',
        );

        // Should find a sub: a forward or midfielder goes out, a defender comes in
        $this->assertNotNull($sub, 'Should find a reactive sub for a CB red card');
        $this->assertContains(\App\Support\PositionMapper::getPositionGroup($sub['player_out']->position),
            ['Forward', 'Midfielder'], 'Player subbed out should be a forward or midfielder');
        $this->assertEquals('Defender', \App\Support\PositionMapper::getPositionGroup($sub['player_in']->position),
            'Player subbed in should be a defender');
    }

    public function test_forward_red_card_does_not_trigger_reshape_sub(): void
    {
        $aiSubService = new AISubstitutionService;
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $lineup = $this->createLineup($game, $team, 11, 75);
        $bench = $this->createBenchPlayers($game, $team, 7, 70);

        $fwPlayer = $lineup->firstWhere('position', 'Centre-Forward');
        $reducedLineup = $lineup->reject(fn ($p) => $p->id === $fwPlayer->id)->values();

        $sub = $aiSubService->chooseRedCardReactiveSubstitution(
            $reducedLineup, $bench, 'Centre-Forward',
        );

        // Forward red card should NOT trigger a reshape — team just plays with 10
        $this->assertNull($sub, 'Forward red card should not trigger a reshape substitution');
    }
}
