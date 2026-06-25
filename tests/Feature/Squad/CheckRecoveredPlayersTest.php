<?php

namespace Tests\Feature\Squad;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Squad\Listeners\CheckRecoveredPlayers;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckRecoveredPlayersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();

        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            // Forward-looking: the next match the user is about to play (first
            // league match), days after the actual return date below.
            'current_date' => '2025-08-17',
        ]);
    }

    public function test_recovery_notification_is_dated_to_the_return_date_not_the_next_match(): void
    {
        // Player returns Aug 5, but the next match isn't until Aug 17 — the dead
        // gap between the last pre-season game and the first league game.
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'name' => 'Kylian Mbappé',
                'injury_until' => '2025-08-05',
                'injury_type' => 'Muscle strain',
            ]);

        $this->dispatchAdvanceTo('2025-07-30', '2025-08-17');

        $notification = GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_PLAYER_RECOVERED)
            ->first();

        $this->assertNotNull($notification);
        // Dated to the actual return date, not the forward-looking current_date,
        // so it stays consistent with the "out until 5 Aug" injury notice.
        $this->assertSame('2025-08-05', $notification->game_date->toDateString());
        $this->assertSame($player->id, $notification->metadata['player_id']);

        // And the injury is actually cleared.
        $this->assertNull($player->refresh()->injury_until);
    }

    private function dispatchAdvanceTo(string $previous, string $new): void
    {
        $event = new GameDateAdvanced(
            $this->game,
            Carbon::parse($previous),
            Carbon::parse($new),
        );

        app(CheckRecoveredPlayers::class)->handle($event);
    }
}
