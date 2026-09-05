<?php

namespace Tests\Unit\Competition;

use App\Models\Competition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Domestic cups take their compact labels from config/countries.php; leagues
 * and UEFA competitions keep the model's static map, and anything unmapped
 * falls back to its full name.
 */
class CompetitionDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_domestic_cup_labels_come_from_country_config(): void
    {
        $copa = Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'name' => 'Copa del Rey']);
        $supercopa = Competition::factory()->knockoutCup()->create(['id' => 'ESPSUP', 'name' => 'Supercopa de España']);

        $this->assertSame('Copa del Rey', $copa->shortName());
        $this->assertSame('Copa', $copa->abbreviation());
        $this->assertSame('Supercopa', $supercopa->shortName());
        $this->assertSame('Supercopa', $supercopa->abbreviation());
    }

    public function test_config_overrides_are_honoured(): void
    {
        config(['countries.ES.domestic_cups.ESPCUP.short_name' => 'Copa']);
        config(['countries.ES.domestic_cups.ESPCUP.abbreviation' => 'CdR']);

        $copa = Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'name' => 'Copa del Rey']);

        $this->assertSame('Copa', $copa->shortName());
        $this->assertSame('CdR', $copa->abbreviation());
    }

    public function test_leagues_keep_the_static_map_and_unknown_competitions_fall_back_to_their_name(): void
    {
        $premierLeague = Competition::factory()->league()->create(['id' => 'ENG1', 'name' => 'Premier League', 'country' => 'EN']);
        $unknown = Competition::factory()->knockoutCup()->create(['id' => 'ZZZCUP', 'name' => 'Coupe Inconnue']);

        $this->assertSame('Premier League', $premierLeague->shortName());
        $this->assertSame('Premier', $premierLeague->abbreviation());
        $this->assertSame('Coupe Inconnue', $unknown->shortName());
        $this->assertSame('Coupe Inconnue', $unknown->abbreviation());
    }
}
