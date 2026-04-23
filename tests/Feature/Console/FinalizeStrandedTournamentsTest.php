<?php

namespace Tests\Feature\Console;

use App\Events\TournamentEnded;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\TournamentSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FinalizeStrandedTournamentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Competition $competition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->competition = Competition::factory()->knockoutCup()->create(['id' => 'TEST_CUP']);
    }

    public function test_dispatches_tournament_ended_for_stranded_game(): void
    {
        $game = $this->makeStrandedTournamentGame();
        Event::fake([TournamentEnded::class]);

        $this->artisan('app:finalize-stranded-tournaments')
            ->assertSuccessful();

        Event::assertDispatched(TournamentEnded::class, fn (TournamentEnded $e) => $e->game->id === $game->id);
    }

    public function test_skips_games_with_existing_summary(): void
    {
        $game = $this->makeStrandedTournamentGame();
        TournamentSummary::create([
            'user_id' => $game->user_id,
            'team_id' => $game->team_id,
            'competition_id' => $this->competition->id,
            'original_game_id' => $game->id,
            'result_label' => 'champion',
            'your_record' => [],
            'summary_data' => [],
            'tournament_date' => '2024-08-15',
        ]);
        Event::fake([TournamentEnded::class]);

        $this->artisan('app:finalize-stranded-tournaments')->assertSuccessful();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    public function test_skips_soft_deleted_games(): void
    {
        $game = $this->makeStrandedTournamentGame();
        $game->update(['deleting_at' => now()]);
        Event::fake([TournamentEnded::class]);

        $this->artisan('app:finalize-stranded-tournaments')->assertSuccessful();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    public function test_skips_games_with_unplayed_matches(): void
    {
        $game = $this->makeStrandedTournamentGame();
        GameMatch::factory()
            ->forGame($game)
            ->forCompetition($this->competition)
            ->create(['played' => false]);
        Event::fake([TournamentEnded::class]);

        $this->artisan('app:finalize-stranded-tournaments')->assertSuccessful();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    public function test_skips_career_mode_games(): void
    {
        $game = $this->makeStrandedTournamentGame();
        $game->update(['game_mode' => Game::MODE_CAREER]);
        Event::fake([TournamentEnded::class]);

        $this->artisan('app:finalize-stranded-tournaments')->assertSuccessful();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    public function test_dry_run_does_not_dispatch(): void
    {
        $this->makeStrandedTournamentGame();
        Event::fake([TournamentEnded::class]);

        $this->artisan('app:finalize-stranded-tournaments', ['--dry-run' => true])->assertSuccessful();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    private function makeStrandedTournamentGame(): Game
    {
        $game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => $this->competition->id,
            'game_mode' => Game::MODE_TOURNAMENT,
        ]);

        GameMatch::factory()
            ->forGame($game)
            ->forCompetition($this->competition)
            ->played(1, 0)
            ->create();

        return $game;
    }
}
