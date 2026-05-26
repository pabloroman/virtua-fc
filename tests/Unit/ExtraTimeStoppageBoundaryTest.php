<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Match\Services\MatchSimulator;
use App\Modules\Match\Support\MinuteCoordinates;
use App\Modules\Match\Support\StoppageDurations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

/**
 * Regression test for the "phantom 90+N' goal" bug.
 *
 * The simulator used to compute the end of regulation as `90 + secondHalfStoppage`,
 * ignoring first-half stoppage. When a match had any first-half stoppage,
 * ET goal events landed at raw minutes that decomposed back into
 * SECOND_HALF_STOPPAGE — surfacing in the event feed as "90+N'" while
 * counting toward `home_score_et`/`away_score_et`. An in-ET resimulation
 * would then filter ET-only score by phase tag and silently drop the goal.
 */
class ExtraTimeStoppageBoundaryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLineups;

    public function test_et_goals_never_decompose_into_regulation_stoppage_phase(): void
    {
        $simulator = new MatchSimulator;
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();
        $homePlayers = $this->createLineup($game, $homeTeam, 11, 80);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 80);

        // Pick a stoppage shape that would have triggered the bug: any
        // first-half stoppage > 0 makes the old `90 + shs` boundary fall
        // short of the real end of regulation by `fhs` minutes.
        $stoppage = new StoppageDurations(firstHalf: 3, secondHalf: 5);

        // Many iterations to make sure we exercise multiple goal-minute rolls.
        for ($i = 0; $i < 30; $i++) {
            $result = $simulator->simulateExtraTime(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                stoppage: $stoppage,
            );

            foreach ($result->events as $event) {
                if (! in_array($event->type, ['goal', 'own_goal'], true)) {
                    continue;
                }

                $decomposed = MinuteCoordinates::decomposeWith($event->minute, $stoppage);

                $this->assertNotEquals(
                    \App\Modules\Match\Enums\MatchPhase::SECOND_HALF_STOPPAGE,
                    $decomposed['phase'],
                    "ET goal event at raw minute {$event->minute} decomposed into SECOND_HALF_STOPPAGE — "
                    . "would surface as a phantom '90+N\\'' goal in the feed."
                );

                $this->assertTrue(
                    $decomposed['phase']->isExtraTime(),
                    "ET goal event at raw minute {$event->minute} should be tagged with an ET_* phase, "
                    . "got {$decomposed['phase']->value}."
                );
            }
        }
    }
}
