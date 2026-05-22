<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Playoffs\PrimeraRFEFPlayoffGenerator;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\PromotionRelegationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end tests for the rewritten {@see PromotionRelegationProcessor}.
 *
 * Each test seeds a full Spain-shaped game state (ESP1 / ESP2 / ESP3A / ESP3B),
 * runs the processor, then asserts the on-disk CompetitionEntry / GameStanding /
 * Game.competition_id reflect the planner's intended moves.
 *
 * Pure planner logic is exercised by {@see \Tests\Unit\CountryPromotionRelegationPlannerTest};
 * this file only covers the DB-touching plumbing: snapshot read,
 * executor application, position resort, and the post-execution invariant.
 */
class PromotionRelegationProcessorTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'tier' => 1, 'handler_type' => 'league']);
        Competition::factory()->league()->create(['id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->league()->create(['id' => 'ESP3A', 'tier' => 3, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->league()->create(['id' => 'ESP3B', 'tier' => 3, 'handler_type' => 'league_with_playoff']);
        Competition::factory()->knockoutCup()->create(['id' => 'ESP3PO', 'tier' => 3]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP2',
            'season' => '2025',
            'country' => 'ES',
        ]);
    }

    /**
     * Production bug fix: when a parent club is being relegated from ESP1 into
     * ESP2 in the same season that its reserve is already in ESP2, the planner
     * cascades the reserve down to ESP3 to maintain the strict parent-above-
     * reserve invariant. Before the rewrite this produced "Reserve/parent
     * coexistence invariant violated" in production and required a manual
     * artisan repair.
     */
    public function test_parent_relegating_into_reserve_tier_cascades_reserve_to_esp3(): void
    {
        // ESP1: 20 teams, parent at position 18 (relegating)
        $esp1Teams = [];
        $parent = null;
        for ($i = 1; $i <= 20; $i++) {
            $team = Team::factory()->create(['country' => 'ES']);
            $esp1Teams[$i] = $team;
            if ($i === 18) {
                $parent = $team;
            }
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id,
                'position' => $i, 'played' => 38, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 60 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        // ESP2: 22 teams. Reserve of $parent is at position 10. Player's team at position 11.
        $reserve = Team::factory()->create(['country' => 'ES', 'parent_team_id' => $parent->id]);
        $esp2Teams = [];
        for ($i = 1; $i <= 22; $i++) {
            if ($i === 10) {
                $team = $reserve;
            } elseif ($i === 11) {
                $team = $this->game->team; // user's team
            } else {
                $team = Team::factory()->create(['country' => 'ES']);
            }
            $esp2Teams[$i] = $team;
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id,
                'position' => $i, 'played' => 42, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 70 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        // ESP3A + ESP3B: simulated (player isn't in them)
        $this->seedSimulatedTier('ESP3A', 20);
        $this->seedSimulatedTier('ESP3B', 20);

        // Run the processor
        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        // Parent relegated to ESP2
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $parent->id)
                ->exists(),
            'Parent should now be in ESP2',
        );

        // Reserve cascaded out of ESP2
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $reserve->id)
                ->exists(),
            'Reserve should no longer be in ESP2',
        );

        // Reserve now in ESP3A or ESP3B
        $reserveDestination = CompetitionEntry::where('game_id', $this->game->id)
            ->where('team_id', $reserve->id)
            ->whereIn('competition_id', ['ESP3A', 'ESP3B'])
            ->value('competition_id');
        $this->assertNotNull($reserveDestination, 'Reserve should be in one of ESP3A/ESP3B');

        // Tier sizes preserved
        $this->assertSame(20, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->count());
        $this->assertSame(22, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP2')->count());
        $this->assertSame(20, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3A')->count());
        $this->assertSame(20, CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP3B')->count());
    }

    /**
     * Two-pass / no-cascading-relegation check: when the player is in ESP2
     * (real standings) and ESP1, ESP3A, ESP3B are simulated, the rule that
     * relegates ESP1 teams into ESP2 must NOT cause those teams to then be
     * relegated again into ESP3 by the ESP2↔ESP3 rule. The planner reads all
     * standings before applying any moves, so relegated ESP1 teams aren't
     * visible in ESP2's relegation slot at read time.
     */
    public function test_relegated_esp1_teams_do_not_cascade_to_esp3(): void
    {
        $esp1TeamIds = [];
        for ($i = 0; $i < 20; $i++) {
            $team = Team::factory()->create(['country' => 'ES']);
            $esp1TeamIds[] = $team->id;
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id, 'entry_round' => 1]);
        }
        SimulatedSeason::create([
            'game_id' => $this->game->id, 'season' => '2025', 'competition_id' => 'ESP1', 'results' => $esp1TeamIds,
        ]);

        $esp2TeamIds = [];
        for ($i = 1; $i <= 22; $i++) {
            if ($i === 10) {
                $team = $this->game->team;
            } else {
                $team = Team::factory()->create(['country' => 'ES']);
            }
            $esp2TeamIds[] = $team->id;
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id,
                'position' => $i, 'played' => 42, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 70 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        $this->seedSimulatedTier('ESP3A', 20);
        $this->seedSimulatedTier('ESP3B', 20);

        $expectedRelegatedFromEsp1 = array_slice($esp1TeamIds, 17, 3);

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        foreach ($expectedRelegatedFromEsp1 as $teamId) {
            $this->assertTrue(
                CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP2')->where('team_id', $teamId)->exists(),
                "ESP1-relegated team should land in ESP2",
            );
            $this->assertFalse(
                CompetitionEntry::where('game_id', $this->game->id)->whereIn('competition_id', ['ESP3A', 'ESP3B'])->where('team_id', $teamId)->exists(),
                "ESP1-relegated team must not cascade to ESP3",
            );
        }
    }

    /**
     * Reserve in ESP2 at position 1 (would direct-promote) while parent is mid-
     * table in ESP1 (not relegating). Reserve must be blocked and the next
     * non-reserve gets promoted.
     */
    public function test_reserve_at_top_of_esp2_is_filtered_when_parent_is_in_esp1(): void
    {
        $parent = Team::factory()->create(['country' => 'ES']);
        CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $parent->id, 'entry_round' => 1]);
        GameStanding::create([
            'game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $parent->id,
            'position' => 5, 'played' => 38, 'won' => 18, 'drawn' => 6, 'lost' => 14,
            'goals_for' => 50, 'goals_against' => 40, 'points' => 60,
        ]);
        // Pad ESP1 to 20 teams
        for ($i = 1; $i <= 20; $i++) {
            if ($i === 5) {
                continue;
            }
            $team = Team::factory()->create(['country' => 'ES']);
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP1', 'team_id' => $team->id,
                'position' => $i, 'played' => 38, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 60 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        $reserve = Team::factory()->create(['country' => 'ES', 'parent_team_id' => $parent->id]);
        CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $reserve->id, 'entry_round' => 1]);
        GameStanding::create([
            'game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $reserve->id,
            'position' => 1, 'played' => 42, 'won' => 28, 'drawn' => 8, 'lost' => 6,
            'goals_for' => 90, 'goals_against' => 30, 'points' => 92,
        ]);
        $esp2TopTwo = [];
        for ($i = 2; $i <= 22; $i++) {
            if ($i === 11) {
                $team = $this->game->team;
            } else {
                $team = Team::factory()->create(['country' => 'ES']);
            }
            if ($i <= 3) {
                $esp2TopTwo[] = $team;
            }
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id, 'entry_round' => 1]);
            GameStanding::create([
                'game_id' => $this->game->id, 'competition_id' => 'ESP2', 'team_id' => $team->id,
                'position' => $i, 'played' => 42, 'won' => max(0, 25 - $i), 'drawn' => 5, 'lost' => $i,
                'goals_for' => 70 - $i, 'goals_against' => 20 + $i, 'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }

        $this->seedSimulatedTier('ESP3A', 20);
        $this->seedSimulatedTier('ESP3B', 20);

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->where('team_id', $reserve->id)->exists(),
            'Reserve must not be promoted to ESP1 — parent is there',
        );

        // Positions 2 and 3 should be promoted instead (since playoff slot picks 1 stand-in too).
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)->where('competition_id', 'ESP1')->where('team_id', $esp2TopTwo[0]->id)->exists(),
            'ESP2 position 2 should be promoted',
        );
    }

    private function seedSimulatedTier(string $competitionId, int $count): void
    {
        $teamIds = [];
        for ($i = 0; $i < $count; $i++) {
            $team = Team::factory()->create(['country' => 'ES']);
            $teamIds[] = $team->id;
            CompetitionEntry::create(['game_id' => $this->game->id, 'competition_id' => $competitionId, 'team_id' => $team->id, 'entry_round' => 1]);
        }
        SimulatedSeason::create([
            'game_id' => $this->game->id, 'season' => '2025', 'competition_id' => $competitionId, 'results' => $teamIds,
        ]);
    }

    /**
     * Regression for the ESP3PO bracket-winner promotion bug. PlayoffGeneratorFactory
     * used to register the PrimeraRFEF generator only under its source divisions
     * (ESP3A, ESP3B), but CountrySeasonSnapshotBuilder looks up the generator by
     * playoff_competition (ESP3PO). The lookup returned null, the snapshot reported
     * PlayoffState::NotStarted even after both bracket finals were resolved, and
     * the planner promoted stand-ins (positions 2–3 of ESP3A) instead of the
     * teams sitting on completed CupTie.winner_id rows. The fix registers the
     * generator under the target competition ID too.
     */
    public function test_esp3po_bracket_winners_are_promoted_to_esp2_not_standings_standins(): void
    {
        $this->seedSimulatedTier('ESP1', 20);
        $esp2 = $this->seedRealTier('ESP2', 22, userPosition: 11);
        $esp3a = $this->seedRealTier('ESP3A', 20);
        $esp3b = $this->seedRealTier('ESP3B', 20);

        // Pick bracket winners deliberately away from the stand-in slots
        // (ESP3A positions 2 and 3). Bracket A winner = ESP3A pos 4,
        // Bracket B winner = ESP3B pos 5. With the bug, ESP3A pos 2 and 3
        // would be promoted instead; the chosen winners would stay in ESP3.
        $bracketAWinner = $esp3a[4];
        $bracketBWinner = $esp3b[5];

        // Round 2 (bracket finals). isComplete() only inspects the final
        // round, so the semifinals are not required to drive the snapshot
        // builder; seeding only the finals keeps the test focused.
        CupTie::factory()->forGame($this->game)->inRound(2)
            ->between($bracketAWinner, $esp3a[2])
            ->completed($bracketAWinner, 'aggregate')
            ->create(['competition_id' => 'ESP3PO', 'bracket_position' => PrimeraRFEFPlayoffGenerator::BRACKET_A]);
        CupTie::factory()->forGame($this->game)->inRound(2)
            ->between($bracketBWinner, $esp3b[2])
            ->completed($bracketBWinner, 'aggregate')
            ->create(['competition_id' => 'ESP3PO', 'bracket_position' => PrimeraRFEFPlayoffGenerator::BRACKET_B]);

        $processor = app(PromotionRelegationProcessor::class);
        $processor->process($this->game, new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP2',
        ));

        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $bracketAWinner->id)
                ->exists(),
            'Bracket A winner (ESP3A pos 4) should be promoted to ESP2',
        );
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->where('team_id', $bracketBWinner->id)
                ->exists(),
            'Bracket B winner (ESP3B pos 5) should be promoted to ESP2',
        );

        // The stand-ins the bug picked must not have hitched a ride.
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->whereIn('team_id', [$esp3a[2]->id, $esp3a[3]->id])
                ->exists(),
            'ESP3A positions 2 and 3 (the bug-era stand-ins) must remain in ESP3',
        );

        // Sanity: direct-promotion slots (position 1 of each group) still apply.
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'ESP2')
                ->whereIn('team_id', [$esp3a[1]->id, $esp3b[1]->id])
                ->count() === 2,
            'Both ESP3A pos 1 and ESP3B pos 1 should be directly promoted',
        );
    }

    /**
     * @return array<int, Team> 1-indexed by standings position.
     */
    private function seedRealTier(string $competitionId, int $count, ?int $userPosition = null): array
    {
        $teams = [];
        for ($i = 1; $i <= $count; $i++) {
            $team = ($i === $userPosition)
                ? $this->game->team
                : Team::factory()->create(['country' => 'ES']);
            $teams[$i] = $team;
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'position' => $i,
                'played' => max(38, ($count - 1) * 2),
                'won' => max(0, 25 - $i),
                'drawn' => 5,
                'lost' => $i,
                'goals_for' => max(10, 70 - $i),
                'goals_against' => 20 + $i,
                'points' => max(0, 25 - $i) * 3 + 5,
            ]);
        }
        return $teams;
    }
}
