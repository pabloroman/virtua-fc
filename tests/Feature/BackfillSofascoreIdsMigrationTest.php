<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The 2026_09_07 backfill deadlocked in production against live gameplay, so it
 * was rewritten to run outside a transaction, save by save, over only the saves
 * built from the broken reference data. This pins what that rewrite could get
 * wrong: which saves it visits, and that it still fills every matchable row.
 */
class BackfillSofascoreIdsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_players_in_saves_built_from_the_broken_data(): void
    {
        $broken = Game::factory()->create(['season' => '2026', 'base_season' => '2026']);
        $tournament = Game::factory()->create(['game_mode' => Game::MODE_TOURNAMENT]);
        $untouched = Game::factory()->create(['base_season' => '2025']);

        $filled = collect([$broken, $tournament])->map(fn (Game $game) => $this->playerWithTemplate($game));
        $skipped = $this->playerWithTemplate($untouched);

        // Same save as $broken, but no template carries its transfermarkt_id.
        $unmatched = GamePlayer::factory()->create([
            'game_id' => $broken->id,
            'transfermarkt_id' => 'no-template-'.Str::uuid(),
            'sofascore_id' => null,
        ]);

        $this->runMigration();

        foreach ($filled as $player) {
            $this->assertSame('sofa-'.$player->transfermarkt_id, $player->fresh()->sofascore_id);
        }

        $this->assertNull($skipped->fresh()->sofascore_id);
        $this->assertNull($unmatched->fresh()->sofascore_id);
    }

    /** A player missing its id, with a template that can supply one. */
    private function playerWithTemplate(Game $game): GamePlayer
    {
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'sofascore_id' => null,
        ]);

        DB::table('game_player_templates')->insert([
            'season' => 2026,
            'player_id' => (string) Str::uuid(),
            'position' => 'Central Midfield',
            'transfermarkt_id' => $player->transfermarkt_id,
            'sofascore_id' => 'sofa-'.$player->transfermarkt_id,
        ]);

        return $player;
    }

    private function runMigration(): void
    {
        (require base_path('database/migrations/2026_09_07_000001_backfill_sofascore_ids.php'))->up();
    }
}
