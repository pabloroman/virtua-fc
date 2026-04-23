<?php

namespace Tests\Feature\Tournament;

use App\Events\TournamentEnded;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\TournamentSummary;
use App\Models\User;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Season\Listeners\DetectTournamentEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DetectTournamentEndedTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private GameMatch $finalMatch;
    private Competition $competition;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $this->competition = Competition::factory()->knockoutCup()->create(['id' => 'TEST_CUP']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $this->competition->id,
            'game_mode' => Game::MODE_TOURNAMENT,
        ]);

        $this->finalMatch = GameMatch::factory()
            ->forGame($this->game)
            ->forCompetition($this->competition)
            ->played(1, 0)
            ->create();
    }

    public function test_dispatches_tournament_ended_when_last_match_finalizes(): void
    {
        Event::fake([TournamentEnded::class]);

        $this->handle();

        Event::assertDispatched(TournamentEnded::class, function (TournamentEnded $event) {
            return $event->game->id === $this->game->id;
        });
    }

    public function test_does_not_dispatch_for_career_mode_games(): void
    {
        $this->game->update(['game_mode' => Game::MODE_CAREER]);
        Event::fake([TournamentEnded::class]);

        $this->handle();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    public function test_does_not_dispatch_when_unplayed_matches_remain(): void
    {
        GameMatch::factory()
            ->forGame($this->game)
            ->forCompetition($this->competition)
            ->create(['played' => false]);

        Event::fake([TournamentEnded::class]);

        $this->handle();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    public function test_does_not_dispatch_when_summary_already_exists(): void
    {
        TournamentSummary::create([
            'user_id' => $this->game->user_id,
            'team_id' => $this->game->team_id,
            'competition_id' => $this->competition->id,
            'original_game_id' => $this->game->id,
            'result_label' => 'champion',
            'your_record' => [],
            'summary_data' => [],
            'tournament_date' => '2024-08-15',
        ]);

        Event::fake([TournamentEnded::class]);

        $this->handle();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    public function test_does_not_dispatch_when_game_is_already_soft_deleted(): void
    {
        $this->game->update(['deleting_at' => now()]);

        Event::fake([TournamentEnded::class]);

        $this->handle();

        Event::assertNotDispatched(TournamentEnded::class);
    }

    private function handle(): void
    {
        $event = new MatchFinalized($this->finalMatch, $this->game->fresh(), $this->competition);
        (new DetectTournamentEnded())->handle($event);
    }
}
