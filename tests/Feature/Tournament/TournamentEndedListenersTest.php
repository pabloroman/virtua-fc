<?php

namespace Tests\Feature\Tournament;

use App\Events\TournamentEnded;
use App\Models\ActivationEvent;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use App\Models\TournamentSummary;
use App\Models\User;
use App\Modules\Report\Listeners\CreateTournamentSnapshot;
use App\Modules\Report\Services\TournamentSnapshotService;
use App\Modules\Season\Listeners\RecordTournamentCompletedActivation;
use App\Modules\Season\Listeners\SoftDeleteCompletedTournamentGame;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class TournamentEndedListenersTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $team = Team::factory()->create();
        $competition = Competition::factory()->knockoutCup()->create(['id' => 'TEST_CUP']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $competition->id,
            'game_mode' => Game::MODE_TOURNAMENT,
        ]);
    }

    public function test_record_activation_listener_inserts_activation_event(): void
    {
        $listener = new RecordTournamentCompletedActivation(app(ActivationTracker::class));

        $listener->handle(new TournamentEnded($this->game));

        $this->assertDatabaseHas('activation_events', [
            'user_id' => $this->game->user_id,
            'game_id' => $this->game->id,
            'game_mode' => Game::MODE_TOURNAMENT,
            'event' => ActivationEvent::EVENT_TOURNAMENT_COMPLETED,
        ]);
    }

    public function test_create_snapshot_listener_calls_snapshot_service(): void
    {
        $snapshotService = Mockery::mock(TournamentSnapshotService::class);
        $snapshotService->shouldReceive('createSnapshot')
            ->once()
            ->with(Mockery::on(fn (Game $g) => $g->id === $this->game->id));

        $listener = new CreateTournamentSnapshot($snapshotService);

        $listener->handle(new TournamentEnded($this->game));
    }

    public function test_create_snapshot_listener_skips_when_summary_already_exists(): void
    {
        TournamentSummary::create([
            'user_id' => $this->game->user_id,
            'team_id' => $this->game->team_id,
            'competition_id' => $this->game->competition_id,
            'original_game_id' => $this->game->id,
            'result_label' => 'champion',
            'your_record' => [],
            'summary_data' => [],
            'tournament_date' => '2024-08-15',
        ]);

        $snapshotService = Mockery::mock(TournamentSnapshotService::class);
        $snapshotService->shouldNotReceive('createSnapshot');

        $listener = new CreateTournamentSnapshot($snapshotService);

        $listener->handle(new TournamentEnded($this->game));
    }

    public function test_soft_delete_listener_marks_game_deleting(): void
    {
        Bus::fake();

        $listener = new SoftDeleteCompletedTournamentGame(app(GameDeletionService::class));

        $listener->handle(new TournamentEnded($this->game));

        $this->assertNotNull($this->game->refresh()->deleting_at);
    }
}
