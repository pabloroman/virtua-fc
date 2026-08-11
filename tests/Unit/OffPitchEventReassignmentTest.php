<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

/**
 * Regression tests for the "off-pitch event" family of bugs.
 *
 * Cards, goals, own goals, assists and missed penalties are generated against
 * the current lineup for a whole period, using random minutes across the period.
 * When an injury substitution (or any substitution) happens mid-period, the
 * replaced player must not appear in later events — otherwise the user sees
 * nonsensical output like "yellow card for Vinicius at 70'" when Vinicius was
 * substituted off at 40' due to injury.
 *
 * These tests crank the injury and card rates so every simulation produces the
 * conditions that previously triggered the bug, then assert that no event
 * occurs after a player's sub-out minute (or before their entry minute).
 */
class OffPitchEventReassignmentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLineups;

    private MatchSimulator $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->simulator = new MatchSimulator;
    }

    public function test_no_event_lands_on_a_player_who_has_already_been_subbed_off(): void
    {
        // Force an injury every match so the auto-sub path is always exercised,
        // and crank yellows so at least one card lands in the same period as
        // the injury auto-sub.
        config([
            'match_simulation.injury_chance' => 100,
            'match_simulation.yellow_cards_per_team' => 6,
        ]);

        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);
        $homeBench = $this->createBenchPlayers($game, $homeTeam, 7, 72);
        $awayBench = $this->createBenchPlayers($game, $awayTeam, 7, 72);

        for ($i = 0; $i < 30; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_4_2, Formation::F_4_4_2,
                Mentality::BALANCED, Mentality::BALANCED,
                $game,
                homeBenchPlayers: $homeBench,
                awayBenchPlayers: $awayBench,
            );

            $this->assertEventsConsistentWithSubstitutions($output->result->events, "iteration $i");
        }
    }

    public function test_no_event_lands_on_a_substitute_before_they_enter_the_pitch(): void
    {
        config([
            'match_simulation.injury_chance' => 100,
            'match_simulation.yellow_cards_per_team' => 6,
        ]);

        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);
        $homeBench = $this->createBenchPlayers($game, $homeTeam, 7, 72);
        $awayBench = $this->createBenchPlayers($game, $awayTeam, 7, 72);

        for ($i = 0; $i < 20; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_4_2, Formation::F_4_4_2,
                Mentality::BALANCED, Mentality::BALANCED,
                $game,
                homeBenchPlayers: $homeBench,
                awayBenchPlayers: $awayBench,
            );

            $enteredAt = [];
            foreach ($output->result->substitutions() as $sub) {
                $playerInId = $sub->metadata['player_in_id'] ?? null;
                if ($playerInId !== null) {
                    $enteredAt[$playerInId] = $sub->minute;
                }
            }

            foreach ($output->result->events as $event) {
                if (! isset($enteredAt[$event->gamePlayerId])) {
                    continue;
                }
                if ($event->type === 'substitution') {
                    continue;
                }
                $this->assertGreaterThanOrEqual(
                    $enteredAt[$event->gamePlayerId],
                    $event->minute,
                    "A '{$event->type}' event at minute {$event->minute} was assigned to a player who only entered at minute {$enteredAt[$event->gamePlayerId]} (iteration $i)",
                );
            }
        }
    }

    public function test_a_player_never_receives_two_yellows_without_being_sent_off(): void
    {
        // Crank yellows and injuries to maximise the chance of the reassignment
        // pass landing a "redirected" yellow on a teammate who was already
        // booked earlier in the match. Without the second-yellow check inside
        // reassignEventsFromUnavailablePlayers, this would produce two yellow
        // events on the same player with no accompanying red.
        config([
            'match_simulation.injury_chance' => 100,
            'match_simulation.yellow_cards_per_team' => 6,
        ]);

        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $homeTeam = Team::factory()->create();
        $awayTeam = Team::factory()->create();

        $homePlayers = $this->createLineup($game, $homeTeam, 11, 75);
        $awayPlayers = $this->createLineup($game, $awayTeam, 11, 75);
        $homeBench = $this->createBenchPlayers($game, $homeTeam, 7, 72);
        $awayBench = $this->createBenchPlayers($game, $awayTeam, 7, 72);

        for ($i = 0; $i < 30; $i++) {
            $output = $this->simulator->simulate(
                $homeTeam, $awayTeam,
                $homePlayers, $awayPlayers,
                Formation::F_4_4_2, Formation::F_4_4_2,
                Mentality::BALANCED, Mentality::BALANCED,
                $game,
                homeBenchPlayers: $homeBench,
                awayBenchPlayers: $awayBench,
            );

            $yellowsByPlayer = [];
            $redsByPlayer = [];
            foreach ($output->result->events as $event) {
                if ($event->type === 'yellow_card') {
                    $yellowsByPlayer[$event->gamePlayerId] = ($yellowsByPlayer[$event->gamePlayerId] ?? 0) + 1;
                } elseif ($event->type === 'red_card') {
                    $redsByPlayer[$event->gamePlayerId] = true;
                }
            }

            foreach ($yellowsByPlayer as $playerId => $count) {
                $this->assertLessThanOrEqual(
                    1,
                    $count,
                    "Player $playerId received $count yellow cards in a single match without being expelled (iteration $i). A second yellow must convert into a red_card with second_yellow=true.",
                );

                if ($count >= 2) {
                    // Belt-and-braces: even if the count assertion above were to
                    // be relaxed in the future, two yellows must still be
                    // accompanied by a red card for the same player.
                    $this->assertArrayHasKey(
                        $playerId,
                        $redsByPlayer,
                        "Player $playerId has $count yellow cards but no red_card event (iteration $i).",
                    );
                }
            }
        }
    }

    /**
     * Assert that no gameplay event occurs after the player was subbed off.
     *
     * Exception: the substitution event itself is attributed to the outgoing
     * player and has minute == sub minute, which is correct.
     */
    private function assertEventsConsistentWithSubstitutions(Collection $events, string $context = ''): void
    {
        $subbedOutAt = [];
        foreach ($events as $event) {
            if ($event->type === 'substitution') {
                $outId = $event->gamePlayerId;
                if (! isset($subbedOutAt[$outId]) || $event->minute < $subbedOutAt[$outId]) {
                    $subbedOutAt[$outId] = $event->minute;
                }
            }
        }

        $problematicTypes = ['goal', 'assist', 'yellow_card', 'red_card', 'own_goal', 'penalty_missed'];

        foreach ($events as $event) {
            /** @var MatchEventData $event */
            if (! in_array($event->type, $problematicTypes, true)) {
                continue;
            }
            if (! isset($subbedOutAt[$event->gamePlayerId])) {
                continue;
            }
            $this->assertLessThan(
                $subbedOutAt[$event->gamePlayerId],
                $event->minute,
                sprintf(
                    "A '%s' event at minute %d was assigned to a player already substituted off at minute %d (%s)",
                    $event->type,
                    $event->minute,
                    $subbedOutAt[$event->gamePlayerId],
                    $context,
                ),
            );
        }
    }
}
