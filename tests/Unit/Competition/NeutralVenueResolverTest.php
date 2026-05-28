<?php

namespace Tests\Unit\Competition;

use App\Models\Team;
use App\Modules\Competition\Services\NeutralVenueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NeutralVenueResolverTest extends TestCase
{
    use RefreshDatabase;

    private NeutralVenueResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new NeutralVenueResolver();
    }

    public function test_espcup_final_is_played_at_la_cartuja(): void
    {
        $venue = $this->resolver->resolve('ESPCUP', 'cup.final', 'home', 'away');

        $this->assertNotNull($venue);
        $this->assertSame('La Cartuja', $venue['name']);
        $this->assertSame(70000, $venue['capacity']);
    }

    public function test_espcup_non_final_round_has_no_neutral_venue(): void
    {
        $this->assertNull($this->resolver->resolve('ESPCUP', 'cup.semi_finals', 'home', 'away'));
    }

    public function test_every_spanish_supercup_game_is_played_in_saudi_arabia(): void
    {
        $expected = ['name' => 'King Abdullah Sports City Stadium', 'capacity' => 62345];

        $this->assertSame($expected, $this->resolver->resolve('ESPSUP', 'cup.semi_finals', 'home', 'away'));
        $this->assertSame($expected, $this->resolver->resolve('ESPSUP', 'cup.final', 'home', 'away'));
    }

    public function test_uefa_final_uses_a_random_neutral_club_ground_over_50k(): void
    {
        $home = Team::factory()->create(['stadium_seats' => 80000]);
        $away = Team::factory()->create(['stadium_seats' => 75000]);
        $neutral = Team::factory()->create([
            'stadium_name' => 'San Siro',
            'stadium_seats' => 60000,
        ]);

        foreach (['UCL', 'UEL', 'UECL', 'UEFASUP'] as $competitionId) {
            $venue = $this->resolver->resolve($competitionId, 'cup.final', $home->id, $away->id);

            $this->assertNotNull($venue);
            $this->assertSame('San Siro', $venue['name']);
            $this->assertSame(60000, $venue['capacity']);
        }
    }

    public function test_uefa_final_never_uses_a_finalists_ground(): void
    {
        // Only the two finalists clear the 50k bar — the resolver must still
        // pick a neutral ground rather than one of theirs.
        $home = Team::factory()->create(['stadium_seats' => 81000]);
        $away = Team::factory()->create(['stadium_seats' => 80000]);
        Team::factory()->create(['stadium_seats' => 10000]); // ineligible (too small)

        $venue = $this->resolver->resolve('UCL', 'cup.final', $home->id, $away->id);

        $this->assertNotNull($venue);
        $this->assertNotSame($home->stadium_name, $venue['name']);
        $this->assertNotSame($away->stadium_name, $venue['name']);
    }

    public function test_uefa_final_falls_back_to_a_guaranteed_venue_when_no_ground_is_eligible(): void
    {
        // No eligible (>=50k, non-finalist) stadium exists at all.
        $home = Team::factory()->create(['stadium_seats' => 20000]);
        $away = Team::factory()->create(['stadium_seats' => 18000]);

        $venue = $this->resolver->resolve('UCL', 'cup.final', $home->id, $away->id);

        $this->assertNotNull($venue);
        $this->assertSame('Wembley Stadium', $venue['name']);
        $this->assertSame(90000, $venue['capacity']);
    }

    public function test_non_neutral_competition_returns_null(): void
    {
        $this->assertNull($this->resolver->resolve('ESP1', 'cup.final', 'home', 'away'));
    }
}
