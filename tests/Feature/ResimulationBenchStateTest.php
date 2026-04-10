<?php

namespace Tests\Feature;

use App\Modules\Lineup\Services\SubstitutionService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the "duplicate substitution" bug.
 *
 * Before the fix, loadTeamsForResimulation computed the opponent lineup/bench
 * by applying every sub from $match->substitutions to the initial lineup and
 * treating "squad − final lineup" as the bench. This put subbed-out starters
 * back on the bench, letting the AI pick them again during resimulation —
 * producing match_events where the same player was brought on twice.
 */
class ResimulationBenchStateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;

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

        $this->playerTeam->competitions()->attach($this->competition->id, ['season' => '2024']);
        $this->opponentTeam->competitions()->attach($this->competition->id, ['season' => '2024']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2024-08-15',
        ]);
    }

    public function test_subbed_out_opponent_starter_does_not_reappear_on_the_bench(): void
    {
        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        // Take 11 opponent players as the starting lineup. Pick one starter
        // (the first centre-back) to be "subbed out" during the match.
        $opponentPlayers = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->opponentTeam->id)
            ->orderBy('position')
            ->limit(11)
            ->get();

        $opponentLineupIds = $opponentPlayers->pluck('id')->toArray();
        $subbedOutStarter = $opponentPlayers
            ->firstWhere('position', 'Centre-Back');
        $this->assertNotNull($subbedOutStarter, 'Test setup needs a centre-back starter');

        // Create a replacement bench player to come on in their place
        $replacement = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->opponentTeam)
            ->create(['position' => 'Centre-Back']);

        $userLineupIds = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
            'played' => true,
            'home_lineup' => $userLineupIds,
            'away_lineup' => $opponentLineupIds,
            // Simulate the state after the initial background simulation:
            // the JSON records an opponent sub that also exists in match_events.
            'substitutions' => [
                [
                    'team_id' => $this->opponentTeam->id,
                    'player_out_id' => $subbedOutStarter->id,
                    'player_in_id' => $replacement->id,
                    'minute' => 66,
                    'auto' => true,
                ],
            ],
        ]);

        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $subbedOutStarter->id,
            'team_id' => $this->opponentTeam->id,
            'minute' => 66,
            'event_type' => MatchEvent::TYPE_SUBSTITUTION,
            'metadata' => ['player_in_id' => $replacement->id],
        ]);

        $userLineup = GamePlayer::with('player')->whereIn('id', $userLineupIds)->get();

        $service = app(SubstitutionService::class);

        // Resim without a specific minute (full-match reconstruction). The
        // subbed-out starter must be in neither the lineup nor the bench;
        // the replacement should be on the pitch.
        $result = $service->loadTeamsForResimulation(
            $match,
            $this->game,
            $userLineup,
            []
        );

        $awayLineupIds = $result['awayPlayers']->pluck('id')->toArray();
        $awayBenchIds = $result['awayBench']->pluck('id')->toArray();

        $this->assertNotContains(
            $subbedOutStarter->id,
            $awayBenchIds,
            'A subbed-out starter must never reappear on the bench — '
            .'otherwise the AI can re-pick them as a replacement, creating '
            .'duplicate sub-in events for the same player.'
        );
        $this->assertNotContains(
            $subbedOutStarter->id,
            $awayLineupIds,
            'A subbed-out starter must not be in the reconstructed lineup'
        );
        $this->assertContains(
            $replacement->id,
            $awayLineupIds,
            'The replacement should be on the pitch in the reconstructed lineup'
        );

        // Resim at minute 50 (before the sub). The starter should still
        // be on the pitch; the replacement should still be on the bench.
        $result = $service->loadTeamsForResimulation(
            $match,
            $this->game,
            $userLineup,
            [],
            50,
        );

        $awayLineupIds = $result['awayPlayers']->pluck('id')->toArray();
        $awayBenchIds = $result['awayBench']->pluck('id')->toArray();

        $this->assertContains(
            $subbedOutStarter->id,
            $awayLineupIds,
            'Before the sub minute, the starter should still be on the pitch'
        );
        $this->assertNotContains(
            $subbedOutStarter->id,
            $awayBenchIds,
            'The starter must not appear on the bench while still on the pitch'
        );
        $this->assertContains(
            $replacement->id,
            $awayBenchIds,
            'Before the sub minute, the replacement should still be on the bench'
        );
    }

    private function createSquad(Team $team): void
    {
        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->goalkeeper()
            ->create();

        foreach (['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back'] as $position) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $position]);
        }

        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->count(4)
            ->create(['position' => 'Central Midfield']);

        foreach (['Centre-Forward', 'Centre-Forward'] as $position) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $position]);
        }
    }
}
