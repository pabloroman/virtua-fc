<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Lineup\Services\SubstitutionService;
use App\Modules\Match\Services\AISubstitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

class AISubstitutionServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLineups;

    private AISubstitutionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AISubstitutionService;
    }

    public function test_decide_total_subs_returns_between_3_and_5(): void
    {
        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $this->service->decideTotalSubs(7);
        }

        $this->assertGreaterThanOrEqual(3, min($results));
        $this->assertLessThanOrEqual(5, max($results));
    }

    public function test_decide_total_subs_respects_already_used(): void
    {
        // If 4 subs already used, max 1 more
        $results = [];
        for ($i = 0; $i < 50; $i++) {
            $results[] = $this->service->decideTotalSubs(7, 4);
        }

        $this->assertLessThanOrEqual(1, max($results));
    }

    public function test_decide_total_subs_returns_zero_when_all_used(): void
    {
        $this->assertEquals(0, $this->service->decideTotalSubs(7, 5));
    }

    public function test_decide_total_subs_limited_by_bench_size(): void
    {
        // Only 2 bench players available
        $results = [];
        for ($i = 0; $i < 50; $i++) {
            $results[] = $this->service->decideTotalSubs(2);
        }

        $this->assertLessThanOrEqual(2, max($results));
    }

    public function test_generate_substitution_windows_produces_valid_minutes(): void
    {
        $config = config('match_simulation.ai_substitutions');

        for ($i = 0; $i < 50; $i++) {
            $windows = $this->service->generateSubstitutionWindows(4);

            foreach (array_keys($windows) as $minute) {
                // Halftime (46) or within configured range
                $this->assertTrue(
                    $minute === 46 || ($minute >= $config['min_minute'] && $minute <= $config['max_minute']),
                    "Sub minute $minute should be 46 (halftime) or within [{$config['min_minute']}, {$config['max_minute']}]"
                );
            }
        }
    }

    public function test_generate_substitution_windows_respects_max_windows(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $windows = $this->service->generateSubstitutionWindows(5);

            $this->assertLessThanOrEqual(
                SubstitutionService::MAX_WINDOWS,
                count($windows),
                'Should never exceed ' . SubstitutionService::MAX_WINDOWS . ' substitution windows'
            );
        }
    }

    public function test_generate_substitution_windows_total_subs_matches(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $targetSubs = 4;
            $windows = $this->service->generateSubstitutionWindows($targetSubs);

            $totalSubs = array_sum(array_map('count', $windows));
            $this->assertEquals($targetSubs, $totalSubs, "Total subs across windows should equal $targetSubs");
        }
    }

    public function test_choose_substitutions_never_subs_goalkeeper(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $lineup = $this->createLineup($game, $team, 11, 70);
        $bench = $this->createBenchPlayers($game, $team, 5, 75);

        for ($i = 0; $i < 20; $i++) {
            $subs = $this->service->chooseSubstitutions(
                $lineup, $bench, 70, 3, 0, [], 1.0, $game->current_date,
            );

            foreach ($subs as $sub) {
                $this->assertNotEquals(
                    'Goalkeeper', $sub['player_out']->position,
                    'Should never substitute out the goalkeeper'
                );
            }
        }
    }

    public function test_choose_substitutions_returns_correct_count(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $lineup = $this->createLineup($game, $team, 11, 70);
        $bench = $this->createBenchPlayers($game, $team, 5, 75);

        $subs = $this->service->chooseSubstitutions(
            $lineup, $bench, 70, 2, 0, [], 1.0, $game->current_date,
        );

        $this->assertCount(2, $subs);
    }

    public function test_choose_substitutions_limited_by_bench(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $lineup = $this->createLineup($game, $team, 11, 70);
        // Create a single outfield bench player (not GK — GKs are excluded from replacements)
        $benchPlayer = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'position' => 'Central Midfield',
            'game_technical_ability' => 75,
            'game_physical_ability' => 75,
            'fitness' => 95,
            'morale' => 80,
        ]);
        $benchPlayer->setRelation('game', $game);
        $bench = collect([$benchPlayer]);

        $subs = $this->service->chooseSubstitutions(
            $lineup, $bench, 70, 3, 0, [], 1.0, $game->current_date,
        );

        $this->assertCount(1, $subs, 'Cannot make more subs than bench players available');
    }

    public function test_choose_substitutions_prefers_tired_players(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        // Create lineup with one very low-physical player (tires fast)
        $lineup = collect();
        $lineup->push(GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'position' => 'Goalkeeper',
            'game_technical_ability' => 70,
            'game_physical_ability' => 70,
            'fitness' => 95,
            'morale' => 80,
        ])->setRelation('game', $game));

        // High physical = less tired
        for ($i = 0; $i < 9; $i++) {
            $positions = ['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back', 'Central Midfield', 'Central Midfield', 'Defensive Midfield', 'Right Winger', 'Left Winger'];
            $lineup->push(GamePlayer::factory()->forGame($game)->forTeam($team)->create([
                'position' => $positions[$i],
                'game_technical_ability' => 70,
                'game_physical_ability' => 90,
                'fitness' => 95,
                'morale' => 80,
            ])->setRelation('game', $game));
        }

        // Low physical = very tired by minute 80
        $tiredPlayer = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'position' => 'Centre-Forward',
            'game_technical_ability' => 70,
            'game_physical_ability' => 30,
            'fitness' => 95,
            'morale' => 80,
        ]);
        $tiredPlayer->setRelation('game', $game);
        $lineup->push($tiredPlayer);

        $bench = $this->createBenchPlayers($game, $team, 3, 75);

        $tiredSubbedOut = 0;
        $iterations = 30;

        for ($i = 0; $i < $iterations; $i++) {
            $subs = $this->service->chooseSubstitutions(
                $lineup, $bench, 80, 1, 0, [], 1.0, $game->current_date,
            );

            if ($subs[0]['player_out']->id === $tiredPlayer->id) {
                $tiredSubbedOut++;
            }
        }

        // The tired player should be subbed out most of the time
        $this->assertGreaterThan(
            $iterations * 0.5,
            $tiredSubbedOut,
            'Tired player (low physical) should be subbed out more often than not'
        );
    }

    public function test_choose_substitutions_no_duplicate_players(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $lineup = $this->createLineup($game, $team, 11, 70);
        $bench = $this->createBenchPlayers($game, $team, 5, 75);

        $subs = $this->service->chooseSubstitutions(
            $lineup, $bench, 70, 3, 0, [], 1.0, $game->current_date,
        );

        $outIds = array_map(fn ($s) => $s['player_out']->id, $subs);
        $inIds = array_map(fn ($s) => $s['player_in']->id, $subs);

        $this->assertCount(count($outIds), array_unique($outIds), 'Each player should only be subbed out once');
        $this->assertCount(count($inIds), array_unique($inIds), 'Each bench player should only be brought on once');
        $this->assertEmpty(array_intersect($outIds, $inIds), 'A player subbed in should never be subbed back out');
    }

    public function test_previously_subbed_in_players_cannot_be_subbed_out(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $team = Team::factory()->create();

        $lineup = $this->createLineup($game, $team, 11, 70);
        $bench = $this->createBenchPlayers($game, $team, 5, 75);

        // Simulate first window: sub in a bench player
        $firstWindowSubs = $this->service->chooseSubstitutions(
            $lineup, $bench, 60, 1, 0, [], 1.0, $game->current_date,
        );

        $this->assertNotEmpty($firstWindowSubs, 'First window should produce a substitution');

        $playerIn = $firstWindowSubs[0]['player_in'];
        $playerOut = $firstWindowSubs[0]['player_out'];

        // Update lineup and bench as MatchSimulator would
        $updatedLineup = $lineup->reject(fn ($p) => $p->id === $playerOut->id)
            ->push($playerIn)->values();
        $updatedBench = $bench->reject(fn ($p) => $p->id === $playerIn->id)->values();

        // Second window: pass the subbed-in player's ID as previously subbed in
        $secondWindowSubs = $this->service->chooseSubstitutions(
            $updatedLineup, $updatedBench, 75, 2, 0, [], 1.0, $game->current_date,
            previouslySubbedInIds: [$playerIn->id],
        );

        $secondOutIds = array_map(fn ($s) => $s['player_out']->id, $secondWindowSubs);

        $this->assertNotContains(
            $playerIn->id,
            $secondOutIds,
            'A player subbed in during a previous window should not be subbed out later'
        );
    }
}
