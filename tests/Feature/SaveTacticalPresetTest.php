<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTacticalPreset;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaveTacticalPresetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Game $game;

    private Team $team;

    /** @var list<string> */
    private array $elevenPlayerIds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-09-01',
        ]);

        $this->elevenPlayerIds = [];
        for ($i = 0; $i < 11; $i++) {
            $player = Player::factory()->create();
            $gp = GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'player_id' => $player->id,
                'team_id' => $this->team->id,
                'position' => $i === 0 ? 'Goalkeeper' : 'Central Midfield',
            ]);
            $this->elevenPlayerIds[] = $gp->id;
        }
    }

    public function test_post_with_preset_id_updates_existing_preset(): void
    {
        $preset = $this->createPreset('Slot A', '4-3-3', 1);

        $response = $this->actingAs($this->user)->post(
            route('game.tactical-presets.save', $this->game->id),
            $this->presetPayload([
                'name' => 'Renamed A',
                'formation' => '4-4-2',
                'preset_id' => $preset->id,
            ])
        );

        $response->assertRedirect(route('game.lineup', $this->game->id));
        $response->assertSessionHas('success');

        $preset->refresh();
        $this->assertSame('Renamed A', $preset->name);
        $this->assertSame('4-4-2', $preset->formation);
    }

    public function test_post_without_preset_id_fails_when_three_presets_exist(): void
    {
        $this->createPreset('One', '4-3-3', 1);
        $this->createPreset('Two', '4-3-3', 2);
        $this->createPreset('Three', '4-3-3', 3);

        $response = $this->actingAs($this->user)->post(
            route('game.tactical-presets.save', $this->game->id),
            $this->presetPayload([
                'name' => 'Fourth',
            ])
        );

        $response->assertRedirect(route('game.lineup', $this->game->id));
        $response->assertSessionHasErrors();

        $this->assertSame(3, GameTacticalPreset::where('game_id', $this->game->id)->count());
    }

    private function createPreset(string $name, string $formation, int $sortOrder): GameTacticalPreset
    {
        return GameTacticalPreset::create([
            'id' => (string) Str::uuid(),
            'game_id' => $this->game->id,
            'name' => $name,
            'sort_order' => $sortOrder,
            'formation' => $formation,
            'lineup' => $this->elevenPlayerIds,
            'slot_assignments' => null,
            'pitch_positions' => null,
            'mentality' => 'balanced',
            'playing_style' => 'balanced',
            'pressing' => 'standard',
            'defensive_line' => 'normal',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function presetPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'My Tactic',
            'formation' => '4-3-3',
            'lineup' => $this->elevenPlayerIds,
            'slot_assignments' => 'null',
            'pitch_positions' => 'null',
            'mentality' => 'balanced',
            'playing_style' => 'balanced',
            'pressing' => 'standard',
            'defensive_line' => 'normal',
        ], $overrides);
    }
}
