<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Match\Services\MatchNarrativeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the retuned mood thresholds. Morale is clamped to [50,100] (default 80),
 * so the narrative signals only make sense inside that band: `morale_high` must
 * be earned (a winning run), not read off the default, and `morale_low` must be
 * reachable (a slumping side). The cut-offs are `> 85` and `< 62`, with a quiet
 * 62–85 neutral zone that the default 80 sits in.
 */
class MatchNarrativeMoodTest extends TestCase
{
    use RefreshDatabase;

    /** Reflect the private mood selector and return just the candidate keys. */
    private function moodKeys(Game $game): array
    {
        $service = new MatchNarrativeService();
        $method = new ReflectionMethod($service, 'moodCandidates');
        $method->setAccessible(true);

        return array_map(fn ($c) => $c['key'], $method->invoke($service, $game));
    }

    /**
     * Seed a squad whose average morale is the given value, with fitness pinned
     * to the neutral band (55–80) so no fitness signal interferes.
     */
    private function seedGame(int $morale): Game
    {
        $team = Team::factory()->create();
        $game = Game::factory()->forTeam($team)->create(['current_date' => '2025-10-01']);

        GamePlayer::factory()->count(11)->forGame($game)->forTeam($team)->create([
            'morale' => $morale,
            'fitness' => 70,
        ]);

        return $game;
    }

    public function test_slumping_squad_surfaces_morale_low(): void
    {
        $keys = $this->moodKeys($this->seedGame(55));

        $this->assertContains('morale_low', $keys);
        $this->assertNotContains('morale_high', $keys);
    }

    public function test_default_morale_sits_in_the_neutral_zone(): void
    {
        $keys = $this->moodKeys($this->seedGame(80));

        $this->assertNotContains('morale_low', $keys);
        $this->assertNotContains('morale_high', $keys);
    }

    public function test_high_morale_only_above_eighty_five(): void
    {
        // Just above the old threshold but inside the new neutral zone: no signal.
        $this->assertNotContains('morale_high', $this->moodKeys($this->seedGame(80)));

        // Clearly above the new cut-off: earned.
        $keys = $this->moodKeys($this->seedGame(90));
        $this->assertContains('morale_high', $keys);
        $this->assertNotContains('morale_low', $keys);
    }
}
