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
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class RedCardSimulationTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * Create a lineup of GamePlayers for a team.
     */
    private function createLineup(Game $game, Team $team, int $count = 11, int $ability = 70): \Illuminate\Support\Collection
    {
        $positions = [
            'Goalkeeper',
            'Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield', 'Defensive Midfield',
            'Right Winger', 'Left Winger',
            'Centre-Forward',
        ];

        $players = collect();
        for ($i = 0; $i < $count; $i++) {
            $player = GamePlayer::factory()
                ->forGame($game)
                ->forTeam($team)
                ->create([
                    'position' => $positions[$i] ?? 'Central Midfield',
                    'game_technical_ability' => $ability,
                    'game_physical_ability' => $ability,
                    'fitness' => 95,
                    'morale' => 80,
                ]);

            $player->setRelation('game', $game);
            $players->push($player);
        }

        return $players;
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

        // With identical player abilities, per-player average should be similar
        $this->assertEqualsWithDelta($fullStrength, $reducedStrength, 0.05,
            'A 10-player lineup of identical players should have similar per-player strength');
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
            Formation::F_4_4_2, Formation::F_4_4_2,
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

        for ($i = 0; $i < $iterations; $i++) {
            // With red card split
            [$h, $a] = $this->simulateGoalsWithRedCardSplit->invoke(
                $this->simulator,
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_4_2, Formation::F_4_4_2,
                Mentality::BALANCED, Mentality::BALANCED,
                PlayingStyle::BALANCED, PlayingStyle::BALANCED,
                PressingIntensity::STANDARD, PressingIntensity::STANDARD,
                DefensiveLineHeight::NORMAL, DefensiveLineHeight::NORMAL,
                $homeStrength, $awayStrength,
                [], [],
                1.0, 1.0,
                0,
                config('match_simulation.base_goals', 1.3),
                $homeRedCard,
                null,
            );
            $splitHomeGoals += $h;
            $splitAwayGoals += $a;

            // Full-team simulation for comparison
            $result = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_4_2, Formation::F_4_4_2,
                Mentality::BALANCED, Mentality::BALANCED,
                $game,
            );
            $normalHomeGoals += $result->homeScore;
            $normalAwayGoals += $result->awayScore;
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
            $result = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_4_2, Formation::F_4_4_2,
                Mentality::BALANCED, Mentality::BALANCED,
                $game,
            );

            if ($result->redCards()->isNotEmpty()) {
                $redCardSeen = true;

                foreach ($result->redCards() as $event) {
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
}
