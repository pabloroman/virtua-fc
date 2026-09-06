<?php

namespace Tests\Feature;

use App\Modules\Competition\Services\CountryConfig;
use App\Support\SeasonData;
use Tests\TestCase;

/**
 * A country or competition added for a future season is invisible until that
 * season arrives.
 *
 * Without this, declaring one makes every earlier season invalid the moment the
 * config lands: the seeder and app:validate-season enumerate competitions from
 * config/countries.php and demand a data/{season}/{CODE}/ folder for each, so a
 * season that predates the addition can no longer be seeded or validated. The
 * gate is what keeps GAME_SEASON a reversible switch rather than a one-way door.
 */
class SeasonAvailabilityTest extends TestCase
{
    private CountryConfig $countryConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->countryConfig = app(CountryConfig::class);
    }

    public function test_a_country_is_hidden_before_the_season_it_is_introduced_in(): void
    {
        config(['countries.XT' => [
            'name' => 'Testland',
            'from_season' => '2030',
            'tiers' => [1 => ['competition' => 'XT1', 'teams' => 18, 'handler' => 'league']],
        ]]);

        $this->assertNotContains('XT', $this->countryConfig->playableCountryCodes('2029'));
        $this->assertContains('XT', $this->countryConfig->playableCountryCodes('2030'));
        $this->assertContains('XT', $this->countryConfig->playableCountryCodes('2031'));
    }

    public function test_a_country_without_a_from_season_has_always_existed(): void
    {
        $this->assertContains('ES', $this->countryConfig->playableCountryCodes('2025'));
        $this->assertContains('ES', $this->countryConfig->playableCountryCodes('1998'));
    }

    public function test_a_cup_is_hidden_before_the_season_it_is_introduced_in(): void
    {
        config(['countries.ES.domestic_cups.XTCUP' => [
            'handler' => 'knockout_cup',
            'from_season' => '2030',
        ]]);

        $this->assertNotContains('XTCUP', $this->countryConfig->domesticCupIds('ES', '2029'));
        $this->assertContains('XTCUP', $this->countryConfig->domesticCupIds('ES', '2030'));
        $this->assertContains('ESPCUP', $this->countryConfig->domesticCupIds('ES', '2029'));
    }

    public function test_a_supercup_is_hidden_while_its_cup_is(): void
    {
        config(['countries.ES.domestic_cups.ESPSUP.from_season' => '2030']);

        $this->assertNull($this->countryConfig->supercup('ES', '2029'));
        $this->assertNotNull($this->countryConfig->supercup('ES', '2030'));
    }

    public function test_a_transfer_pool_league_is_hidden_before_its_season(): void
    {
        config(['countries.ES.support.transfer_pool.XT1' => [
            'role' => 'league',
            'handler' => 'league',
            'country' => 'XT',
            'from_season' => '2030',
        ]]);

        $this->assertNotContains('XT1', $this->countryConfig->transferPoolIds('ES', '2029'));
        $this->assertContains('XT1', $this->countryConfig->transferPoolIds('ES', '2030'));
    }

    public function test_the_competition_list_a_season_folder_owns_respects_the_gate(): void
    {
        config(['countries.ES.domestic_cups.XTCUP' => [
            'handler' => 'knockout_cup',
            'from_season' => '2030',
        ]]);

        $codesFor = fn (string $season): array => array_column(
            SeasonData::competitions($this->countryConfig, $season),
            'code',
        );

        $this->assertNotContains('XTCUP', $codesFor('2029'));
        $this->assertContains('XTCUP', $codesFor('2030'));
        $this->assertContains('ESPCUP', $codesFor('2029'));
    }

    public function test_the_gate_defaults_to_the_season_the_game_is_seeded_for(): void
    {
        config([
            'season.current' => '2029',
            'countries.ES.domestic_cups.XTCUP' => [
                'handler' => 'knockout_cup',
                'from_season' => '2030',
            ],
        ]);

        $this->assertNotContains('XTCUP', $this->countryConfig->domesticCupIds('ES'));

        config(['season.current' => '2030']);

        $this->assertContains('XTCUP', $this->countryConfig->domesticCupIds('ES'));
    }
}
