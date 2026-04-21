<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\GameTactics;
use App\Models\Team;
use App\Models\User;
use App\Modules\Lineup\Services\LineupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the fast-mode fitness-rotation behaviour for the user's team. Before
 * this change the user's saved lineup was reused verbatim each matchday,
 * leading to exhausted starters while rested alternates sat on the bench.
 *
 * ensureLineupsForMatches() now treats tired preferred starters (fitness below
 * the AI rotation threshold — 70 by default) as candidates for replacement by
 * rested same-position subs, while still preferring a tired specialist over
 * an out-of-position sub when the whole group is tired.
 */
class LineupFitnessRotationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;
    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->playerTeam = Team::factory()->create(['name' => 'Player Team']);
        $this->opponentTeam = Team::factory()->create(['name' => 'Opponent Team']);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2024-09-01',
        ]);

        $this->match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-09-01'),
            'played' => false,
        ]);
    }

    /**
     * Build a full 11-man squad plus named midfield subs. One preferred MC is
     * deliberately exhausted, two same-position subs are rested; the rest of
     * the XI is fresh.
     *
     * @return array{starters: \Illuminate\Support\Collection, tiredMc: GamePlayer, freshSubs: \Illuminate\Support\Collection}
     */
    private function seedSquadWithTiredMidfielder(array $extra = []): array
    {
        $starterSpec = [
            ['position' => 'Goalkeeper', 'fitness' => 95],
            ['position' => 'Left-Back', 'fitness' => 95],
            ['position' => 'Centre-Back', 'fitness' => 95],
            ['position' => 'Centre-Back', 'fitness' => 95],
            ['position' => 'Right-Back', 'fitness' => 95],
            ['position' => 'Central Midfield', 'fitness' => 95],
            ['position' => 'Central Midfield', 'fitness' => 95],
            // The exhausted preferred MC the fast-mode loop must now bench.
            ['position' => 'Central Midfield', 'fitness' => 50, 'game_technical_ability' => 75, 'game_physical_ability' => 75],
            ['position' => 'Left Winger', 'fitness' => 95],
            ['position' => 'Centre-Forward', 'fitness' => 95],
            ['position' => 'Right Winger', 'fitness' => 95],
        ];

        $starters = collect($starterSpec)->map(fn (array $attrs) => GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->playerTeam)
            ->create(array_merge(['morale' => 80], $attrs)));

        $tiredMc = $starters[7];

        $freshSubs = collect([
            GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create([
                'position' => 'Central Midfield',
                'fitness' => 100,
                'game_technical_ability' => 70,
                'game_physical_ability' => 70,
                'morale' => 80,
            ]),
            GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create([
                'position' => 'Central Midfield',
                'fitness' => 100,
                'game_technical_ability' => 68,
                'game_physical_ability' => 68,
                'morale' => 80,
            ]),
        ]);

        return ['starters' => $starters, 'tiredMc' => $tiredMc, 'freshSubs' => $freshSubs];
    }

    private function setPreferredLineup(array $playerIds, string $formation = '4-3-3'): void
    {
        GameTactics::updateOrCreate(
            ['game_id' => $this->game->id],
            [
                'default_formation' => $formation,
                'default_lineup' => $playerIds,
                'default_mentality' => 'balanced',
                'default_playing_style' => 'balanced',
                'default_pressing' => 'standard',
                'default_defensive_line' => 'normal',
            ],
        );

        $this->game->refresh();
    }

    public function test_tired_preferred_starter_is_replaced_by_rested_same_position_sub(): void
    {
        $seed = $this->seedSquadWithTiredMidfielder();
        $preferred = $seed['starters']->pluck('id')->all();
        $this->setPreferredLineup($preferred);

        /** @var LineupService $service */
        $service = app(LineupService::class);

        $service->ensureLineupsForMatches(collect([$this->match]), $this->game);
        $this->match->refresh();

        $lineup = $this->match->home_lineup;

        $this->assertCount(11, $lineup);
        $this->assertNotContains($seed['tiredMc']->id, $lineup, 'Tired preferred MC should have been rotated out.');

        // The rested same-group sub with the higher rating wins the slot.
        $this->assertContains($seed['freshSubs']->first()->id, $lineup);

        // Every other preferred starter is preserved.
        foreach ($seed['starters']->reject(fn ($p) => $p->id === $seed['tiredMc']->id) as $preserved) {
            $this->assertContains($preserved->id, $lineup, "Fresh preferred starter {$preserved->position} should remain in XI.");
        }
    }

    public function test_tired_specialist_is_kept_when_no_rested_same_group_sub_exists(): void
    {
        $seed = $this->seedSquadWithTiredMidfielder();

        // Exhaust every MC we created so no rested same-position sub exists.
        // Fitness lives on the GamePlayerMatchState satellite table, not game_players.
        GamePlayerMatchState::whereIn('game_player_id', $seed['freshSubs']->pluck('id'))
            ->update(['fitness' => 45]);

        $preferred = $seed['starters']->pluck('id')->all();
        $this->setPreferredLineup($preferred);

        /** @var LineupService $service */
        $service = app(LineupService::class);

        $service->ensureLineupsForMatches(collect([$this->match]), $this->game);
        $this->match->refresh();

        $lineup = $this->match->home_lineup;

        $this->assertCount(11, $lineup);
        $this->assertContains(
            $seed['tiredMc']->id,
            $lineup,
            'With no rested MC available, the tired preferred specialist must still start.',
        );
    }

    public function test_fresh_preferred_lineup_is_preserved_as_is(): void
    {
        // Build an 11-man squad where everyone is well above the rotation threshold.
        $positions = [
            'Goalkeeper',
            'Left-Back', 'Centre-Back', 'Centre-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield', 'Central Midfield',
            'Left Winger', 'Centre-Forward', 'Right Winger',
        ];

        $starters = collect($positions)->map(fn (string $pos) => GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->playerTeam)
            ->create(['position' => $pos, 'fitness' => 95, 'morale' => 80]));

        // Add an alternate so we have more than 11 total; it must remain benched.
        $alternate = GamePlayer::factory()->forGame($this->game)->forTeam($this->playerTeam)->create([
            'position' => 'Central Midfield',
            'fitness' => 100,
            'morale' => 80,
        ]);

        $preferred = $starters->pluck('id')->all();
        $this->setPreferredLineup($preferred);

        /** @var LineupService $service */
        $service = app(LineupService::class);

        $service->ensureLineupsForMatches(collect([$this->match]), $this->game);
        $this->match->refresh();

        $this->assertSame($preferred, $this->match->home_lineup);
        $this->assertNotContains($alternate->id, $this->match->home_lineup);
    }
}
