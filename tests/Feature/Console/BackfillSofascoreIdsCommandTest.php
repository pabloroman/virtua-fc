<?php

namespace Tests\Feature\Console;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillSofascoreIdsCommandTest extends TestCase
{
    /** A transfermarkt id the shipped crosswalk actually covers. */
    private string $transfermarktId;

    private string $sofascoreId;

    protected function setUp(): void
    {
        parent::setUp();

        $map = json_decode((string) file_get_contents(base_path('data/sofascore_ids.json')), true);
        $this->transfermarktId = (string) array_key_first($map);
        $this->sofascoreId = (string) $map[$this->transfermarktId];
    }

    public function test_it_fills_templates_and_the_saves_that_copied_a_null(): void
    {
        $team = Team::factory()->create();
        $templateId = $this->template($team);
        $player = $this->savedPlayer($team, baseSeason: '2026');

        $this->artisan('app:backfill-sofascore-ids')->assertSuccessful();

        $this->assertSame($this->sofascoreId, DB::table('game_player_templates')->find($templateId)->sofascore_id);
        $this->assertSame($this->sofascoreId, $player->fresh()->sofascore_id);
    }

    public function test_it_leaves_saves_built_before_the_refresh_alone(): void
    {
        $player = $this->savedPlayer(Team::factory()->create(), baseSeason: '2025');

        $this->artisan('app:backfill-sofascore-ids')->assertSuccessful();

        $this->assertNull($player->fresh()->sofascore_id);
    }

    public function test_it_is_a_no_op_on_a_second_run(): void
    {
        $player = $this->savedPlayer(Team::factory()->create(), baseSeason: '2026');

        $this->artisan('app:backfill-sofascore-ids');
        $this->artisan('app:backfill-sofascore-ids')->assertSuccessful();

        $this->assertSame($this->sofascoreId, $player->fresh()->sofascore_id);
    }

    private function template(Team $team): int
    {
        $playerId = Str::uuid()->toString();

        DB::table('players')->insert([
            'id' => $playerId,
            'transfermarkt_id' => $this->transfermarktId,
            'name' => 'Crosswalked Player',
        ]);

        return DB::table('game_player_templates')->insertGetId([
            'season' => '2026',
            'player_id' => $playerId,
            'team_id' => $team->id,
            'transfermarkt_id' => $this->transfermarktId,
            'position' => 'Central Midfield',
            'sofascore_id' => null,
        ]);
    }

    private function savedPlayer(Team $team, string $baseSeason): GamePlayer
    {
        $game = Game::factory()->forTeam($team)->create(['base_season' => $baseSeason]);

        return GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'transfermarkt_id' => $this->transfermarktId,
            'sofascore_id' => null,
        ]);
    }
}
