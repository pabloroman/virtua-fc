<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\Player;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePlayerMatchStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_satellite_row_by_default(): void
    {
        $player = GamePlayer::factory()->create();

        $this->assertNotNull($player->matchState);
        $this->assertGreaterThanOrEqual(70, $player->matchState->fitness);
        $this->assertLessThanOrEqual(100, $player->matchState->fitness);
    }

    public function test_pool_factory_state_skips_satellite_row(): void
    {
        $player = GamePlayer::factory()->pool()->create();

        $this->assertNull($player->matchState);
        $this->assertDatabaseMissing('game_player_match_state', [
            'game_player_id' => $player->id,
        ]);
    }

    public function test_accessor_returns_satellite_value(): void
    {
        $player = GamePlayer::factory()->create();
        GamePlayerMatchState::where('game_player_id', $player->id)->update([
            'fitness' => 55,
            'morale' => 65,
            'goals' => 7,
            'assists' => 3,
            'appearances' => 12,
        ]);

        $player = $player->fresh(['matchState']);

        $this->assertSame(55, $player->fitness);
        $this->assertSame(65, $player->morale);
        $this->assertSame(7, $player->goals);
        $this->assertSame(3, $player->assists);
        $this->assertSame(12, $player->appearances);
    }

    public function test_accessor_returns_defaults_for_pool_player(): void
    {
        $player = GamePlayer::factory()->pool()->create();

        $this->assertSame(GamePlayerMatchState::DEFAULTS['fitness'], $player->fitness);
        $this->assertSame(GamePlayerMatchState::DEFAULTS['morale'], $player->morale);
        $this->assertSame(0, $player->goals);
        $this->assertSame(0, $player->appearances);
        $this->assertNull($player->injury_until);
    }

    public function test_in_memory_override_wins_over_satellite(): void
    {
        $player = GamePlayer::factory()->create();
        GamePlayerMatchState::where('game_player_id', $player->id)->update(['goals' => 5]);
        $player = $player->fresh(['matchState']);

        // Mimics CompetitionViewService stuffing per-competition tallies onto
        // the model for display.
        $clone = clone $player;
        $clone->goals = 99;

        $this->assertSame(99, $clone->goals);
        // The original is unaffected.
        $this->assertSame(5, $player->goals);
    }

    public function test_satellite_cascades_on_game_player_delete(): void
    {
        $player = GamePlayer::factory()->create();

        $this->assertDatabaseHas('game_player_match_state', [
            'game_player_id' => $player->id,
        ]);

        $player->delete();

        $this->assertDatabaseMissing('game_player_match_state', [
            'game_player_id' => $player->id,
        ]);
    }

    public function test_injured_player_is_unavailable(): void
    {
        $game = Game::factory()->create(['current_date' => '2025-10-01']);
        $player = GamePlayer::factory()->forGame($game)->create();

        GamePlayerMatchState::where('game_player_id', $player->id)->update([
            'injury_until' => '2025-10-15',
            'injury_type' => 'Hamstring tear',
        ]);

        $player = $player->fresh(['matchState']);

        $this->assertFalse($player->isAvailable(Carbon::parse('2025-10-01')));
        $this->assertTrue($player->isInjured(Carbon::parse('2025-10-01')));
        $this->assertSame('Hamstring tear', $player->injury_type);
    }

    public function test_overall_score_reads_through_satellite(): void
    {
        $player = GamePlayer::factory()->create([
            'game_technical_ability' => 80,
            'game_physical_ability' => 70,
        ]);
        GamePlayerMatchState::where('game_player_id', $player->id)->update([
            'fitness' => 100,
            'morale' => 100,
        ]);
        $player = $player->fresh(['matchState']);

        // (80*0.35 + 70*0.35 + 100*0.15 + 100*0.15) = 28 + 24.5 + 15 + 15 = 82.5 → 83
        $this->assertSame(83, $player->overall_score);
    }
}
