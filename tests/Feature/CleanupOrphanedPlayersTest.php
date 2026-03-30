<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupOrphanedPlayersTest extends TestCase
{
    use RefreshDatabase;

    private function createOrphanedGeneratedPlayer(): Player
    {
        return Player::create([
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => 'gen-'.Str::uuid()->toString(),
            'name' => 'Orphaned Generated',
            'nationality' => ['Spain'],
            'date_of_birth' => '2000-01-01',
            'technical_ability' => 60,
            'physical_ability' => 60,
        ]);
    }

    private function createOrphanedRealPlayer(): Player
    {
        return Player::create([
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => '99999',
            'name' => 'Orphaned Real',
            'nationality' => ['Spain'],
            'date_of_birth' => '1995-01-01',
            'technical_ability' => 80,
            'physical_ability' => 75,
        ]);
    }

    private function createActiveGeneratedPlayer(): Player
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
        ]);

        $player = Player::create([
            'id' => Str::uuid()->toString(),
            'transfermarkt_id' => 'gen-'.Str::uuid()->toString(),
            'name' => 'Active Generated',
            'nationality' => ['Spain'],
            'date_of_birth' => '2000-01-01',
            'technical_ability' => 60,
            'physical_ability' => 60,
        ]);

        GamePlayer::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'position' => 'Centre-Back',
            'market_value_cents' => 1_000_000_00,
            'contract_until' => '2027-06-30',
            'annual_wage' => 500_000_00,
            'fitness' => 90,
            'morale' => 70,
            'durability' => 80,
            'game_technical_ability' => 60,
            'game_physical_ability' => 60,
            'potential' => 70,
            'potential_low' => 65,
            'potential_high' => 75,
            'season_appearances' => 0,
            'tier' => 3,
            'number' => 5,
        ]);

        return $player;
    }

    public function test_deletes_orphaned_generated_players(): void
    {
        $orphan = $this->createOrphanedGeneratedPlayer();

        $this->artisan('app:cleanup-orphaned-players')
            ->expectsOutputToContain('Deleted 1 orphaned generated player(s)')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('players', ['id' => $orphan->id]);
    }

    public function test_preserves_generated_players_with_active_references(): void
    {
        $activePlayer = $this->createActiveGeneratedPlayer();

        $this->artisan('app:cleanup-orphaned-players')
            ->assertExitCode(0);

        $this->assertDatabaseHas('players', ['id' => $activePlayer->id]);
    }

    public function test_preserves_real_players_even_if_orphaned(): void
    {
        $realPlayer = $this->createOrphanedRealPlayer();

        $this->artisan('app:cleanup-orphaned-players')
            ->assertExitCode(0);

        $this->assertDatabaseHas('players', ['id' => $realPlayer->id]);
    }

    public function test_dry_run_reports_count_without_deleting(): void
    {
        $orphan1 = $this->createOrphanedGeneratedPlayer();
        $orphan2 = $this->createOrphanedGeneratedPlayer();

        $this->artisan('app:cleanup-orphaned-players', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN] Found 2 orphaned generated player(s)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('players', ['id' => $orphan1->id]);
        $this->assertDatabaseHas('players', ['id' => $orphan2->id]);
    }
}
