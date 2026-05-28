<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Match\DTOs\MatchSimulationContext;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

/**
 * Regression tests for the simulateWindow() refactor.
 *
 * simulateWindow is a thin context-driven wrapper around simulateRemainder.
 * These tests pin the wrapper's bookkeeping: context accumulators, event
 * appending, performance-cache survival, score/xG totals, and injury/yellow
 * trackers all behave correctly across one and multiple windows.
 *
 * The simulator's internal randomness uses random_int (not seedable), so we
 * test structural invariants of the wrapper rather than byte-equality with
 * the existing batch path.
 */
class MatchSimulatorWindowedDeterminismTest extends TestCase
{
    use CreatesLineups;
    use RefreshDatabase;

    public function test_single_full_match_window_bookkeeping(): void
    {
        $simulator = new MatchSimulator;
        $ctx = $this->buildContext('pin-single');

        $result = $simulator->simulateWindow($ctx, 0, 93);

        $this->assertSame($result->fromMinute, 0);
        $this->assertSame($result->toMinute, 93);

        // Cumulative state reflects exactly what the window produced.
        $this->assertSame($result->homeScoreDelta, $ctx->homeScore);
        $this->assertSame($result->awayScoreDelta, $ctx->awayScore);
        $this->assertSame($result->homeXG, $ctx->homeXGTotal);
        $this->assertSame($result->awayXG, $ctx->awayXGTotal);
        $this->assertSame($result->newEvents->count(), $ctx->accumulatedEvents->count());

        // Every generated event must fall within the requested minute range.
        foreach ($result->newEvents as $event) {
            $this->assertGreaterThanOrEqual(1, $event->minute);
            $this->assertLessThanOrEqual(93, $event->minute);
        }
    }

    public function test_two_windows_accumulate_state_and_partition_events_by_minute(): void
    {
        $simulator = new MatchSimulator;
        $ctx = $this->buildContext('pin-two');

        $first = $simulator->simulateWindow($ctx, 0, 45);
        $second = $simulator->simulateWindow($ctx, 45, 93);

        // Score and xG totals are sums of the per-window deltas.
        $this->assertSame($first->homeScoreDelta + $second->homeScoreDelta, $ctx->homeScore);
        $this->assertSame($first->awayScoreDelta + $second->awayScoreDelta, $ctx->awayScore);
        $this->assertEqualsWithDelta(
            $first->homeXG + $second->homeXG, $ctx->homeXGTotal, 1e-9,
        );
        $this->assertEqualsWithDelta(
            $first->awayXG + $second->awayXG, $ctx->awayXGTotal, 1e-9,
        );

        // Accumulated event log is the union of both windows.
        $this->assertSame(
            $first->newEvents->count() + $second->newEvents->count(),
            $ctx->accumulatedEvents->count(),
        );

        // Events generated in each window stay in that window's minute range.
        // simulateRemainder generates events for (fromMinute, toMinute], so
        // first-window events sit in (0, 45] and second-window in (45, 93].
        foreach ($first->newEvents as $event) {
            $this->assertGreaterThan(0, $event->minute);
            $this->assertLessThanOrEqual(45, $event->minute);
        }
        foreach ($second->newEvents as $event) {
            $this->assertGreaterThan(45, $event->minute);
            $this->assertLessThanOrEqual(93, $event->minute);
        }
    }

    public function test_performance_cache_survives_across_windows(): void
    {
        $simulator = new MatchSimulator;
        $ctx = $this->buildContext('pin-perf');

        $simulator->simulateWindow($ctx, 0, 45);
        $performanceAfterFirstHalf = $ctx->matchPerformance;

        // First half should have rolled performance for at least the starters.
        $this->assertNotEmpty($performanceAfterFirstHalf);

        $simulator->simulateWindow($ctx, 45, 93);

        // Every player whose performance was rolled in the first half retains
        // the same value in the second half (preservePerformance contract).
        foreach ($performanceAfterFirstHalf as $playerId => $value) {
            $this->assertArrayHasKey($playerId, $ctx->matchPerformance);
            $this->assertSame(
                $value,
                $ctx->matchPerformance[$playerId],
                "Performance for player {$playerId} must be preserved across windows",
            );
        }
    }

    public function test_injury_and_yellow_accumulators_carry_across_windows(): void
    {
        // Crank injury/yellow rates so we reliably hit both kinds of events
        // in the first half and can observe the trackers being populated.
        config([
            'match_simulation.injury_chance' => 100,
            'match_simulation.yellow_cards_per_team' => 6,
        ]);

        $simulator = new MatchSimulator;
        $ctx = $this->buildContext('pin-trackers');

        $first = $simulator->simulateWindow($ctx, 0, 45);

        $expectedInjuryTeams = $first->newEvents
            ->filter(fn ($e) => $e->type === 'injury')
            ->pluck('teamId')
            ->unique()
            ->values()
            ->all();
        $expectedYellowPlayers = $first->newEvents
            ->filter(fn ($e) => $e->type === 'yellow_card')
            ->pluck('gamePlayerId')
            ->unique()
            ->values()
            ->all();

        foreach ($expectedInjuryTeams as $teamId) {
            $this->assertContains($teamId, $ctx->existingInjuryTeamIds);
        }
        foreach ($expectedYellowPlayers as $playerId) {
            $this->assertContains($playerId, $ctx->existingYellowPlayerIds);
        }

        // Trackers must not gain duplicates if the same team/player appears
        // again in the second window.
        $simulator->simulateWindow($ctx, 45, 93);
        $this->assertSame(
            array_values(array_unique($ctx->existingInjuryTeamIds)),
            array_values($ctx->existingInjuryTeamIds),
        );
        $this->assertSame(
            array_values(array_unique($ctx->existingYellowPlayerIds)),
            array_values($ctx->existingYellowPlayerIds),
        );
    }

    private function buildContext(string $seed): MatchSimulationContext
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);

        return new MatchSimulationContext(
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            homePlayers: $homePlayers,
            awayPlayers: $awayPlayers,
            homeFormation: Formation::F_4_4_2,
            awayFormation: Formation::F_4_4_2,
            homeMentality: Mentality::BALANCED,
            awayMentality: Mentality::BALANCED,
            matchSeed: $seed,
            game: $game,
        );
    }
}
