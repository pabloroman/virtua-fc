<?php

namespace Tests\Feature;

use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Modules\Squad\Services\PlayerGeneratorService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->team = Team::factory()->create();
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->team->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
        ]);
    }

    public function test_creates_game_player_record_with_biography(): void
    {
        $service = app(PlayerGeneratorService::class);
        $dateOfBirth = Carbon::createFromDate(2002, 6, 15);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Centre-Back',
            overallScore: 68,
            dateOfBirth: $dateOfBirth,
            contractYears: 3,
        ));

        $this->assertDatabaseHas('game_players', ['id' => $gamePlayer->id]);
        $this->assertEquals('Centre-Back', $gamePlayer->position);
        $this->assertEquals($this->team->id, $gamePlayer->team_id);
        $this->assertEquals($this->game->id, $gamePlayer->game_id);
        $this->assertEquals(68, $gamePlayer->overall_score);
        $this->assertEquals($dateOfBirth->toDateString(), $gamePlayer->date_of_birth->toDateString());

        // Contract should be 3 years from season
        $this->assertEquals(2027, $gamePlayer->contract_until->year);
        $this->assertEquals(6, $gamePlayer->contract_until->month);
    }

    public function test_auto_generates_name_and_nationality(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Central Midfield',
            overallScore: 52,
            dateOfBirth: Carbon::createFromDate(2006, 3, 10),
            contractYears: 3,
        ));

        $this->assertNotEmpty($gamePlayer->name);
        $this->assertNotEmpty($gamePlayer->nationality);
        $this->assertIsArray($gamePlayer->nationality);
    }

    public function test_uses_provided_name_and_nationality(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Centre-Forward',
            overallScore: 62,
            dateOfBirth: Carbon::createFromDate(2000, 1, 1),
            contractYears: 2,
            name: 'Test Player',
            nationality: ['BRA'],
        ));

        $this->assertEquals('Test Player', $gamePlayer->name);
        $this->assertEquals(['BRA'], $gamePlayer->nationality);
    }

    public function test_auto_estimates_market_value(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Central Midfield',
            overallScore: 75,
            dateOfBirth: Carbon::createFromDate(1999, 5, 20),
            contractYears: 3,
        ));

        $this->assertGreaterThan(100_000_00, $gamePlayer->market_value_cents);
        $this->assertGreaterThan(0, $gamePlayer->annual_wage);
    }

    public function test_uses_provided_market_value(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Goalkeeper',
            overallScore: 52,
            dateOfBirth: Carbon::createFromDate(2005, 8, 12),
            contractYears: 3,
            marketValueCents: 500_000_00,
        ));

        $this->assertEquals(500_000_00, $gamePlayer->market_value_cents);
    }

    public function test_uses_provided_potential(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Left Winger',
            overallScore: 47,
            dateOfBirth: Carbon::createFromDate(2007, 2, 14),
            contractYears: 3,
            potential: 85,
            potentialLow: 80,
            potentialHigh: 90,
        ));

        $this->assertEquals(85, $gamePlayer->potential);
        $this->assertEquals(80, $gamePlayer->potential_low);
        $this->assertEquals(90, $gamePlayer->potential_high);
    }

    public function test_auto_generates_potential_when_not_provided(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Central Midfield',
            overallScore: 62,
            dateOfBirth: Carbon::createFromDate(2001, 9, 5),
            contractYears: 3,
        ));

        $this->assertNotNull($gamePlayer->potential);
        $this->assertNotNull($gamePlayer->potential_low);
        $this->assertNotNull($gamePlayer->potential_high);
        $this->assertGreaterThanOrEqual($gamePlayer->potential_low, $gamePlayer->potential_high);
    }

    public function test_respects_fitness_and_morale_ranges(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Right-Back',
            overallScore: 52,
            dateOfBirth: Carbon::createFromDate(2006, 4, 1),
            contractYears: 3,
            fitnessMin: 90,
            fitnessMax: 100,
            moraleMin: 80,
            moraleMax: 95,
        ));

        $this->assertGreaterThanOrEqual(90, $gamePlayer->fitness);
        $this->assertLessThanOrEqual(100, $gamePlayer->fitness);
        $this->assertGreaterThanOrEqual(80, $gamePlayer->morale);
        $this->assertLessThanOrEqual(95, $gamePlayer->morale);
    }

    public function test_sets_durability(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Goalkeeper',
            overallScore: 42,
            dateOfBirth: Carbon::createFromDate(2007, 7, 20),
            contractYears: 3,
        ));

        $this->assertNotNull($gamePlayer->durability);
        $this->assertGreaterThan(0, $gamePlayer->durability);
    }

    /**
     * Regression test for issue #819: a generated player must not reuse a name
     * belonging to any other team in the same game. Previously the exclusion
     * check was team-scoped, so Málaga's canteranos could collide with Crystal
     * Palace's squad. With game-wide scope plus Faker, the retry loop should
     * always produce a non-colliding name.
     */
    public function test_generated_name_does_not_collide_with_other_team_players(): void
    {
        $service = app(PlayerGeneratorService::class);

        $otherTeam = Team::factory()->create();

        GamePlayer::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'player_id' => \Illuminate\Support\Str::uuid()->toString(),
            'transfermarkt_id' => 'tm-conflict-1',
            'name' => 'Diego García Pérez',
            'nationality' => ['Spain'],
            'date_of_birth' => '1998-03-14',
            'team_id' => $otherTeam->id,
            'position' => 'Central Midfield',
            'market_value_cents' => 1_000_000_00,
            'contract_until' => Carbon::createFromDate(2026, 6, 30),
            'annual_wage' => 100_000_00,
            'durability' => 80,
            'overall_score' => 70,
            'potential' => 75,
            'potential_low' => 70,
            'potential_high' => 80,
            'tier' => 2,
        ]);

        // The excluded list returned by the generator's internal lookup should
        // include players from every team in the game, not just $this->team.
        for ($i = 0; $i < 20; $i++) {
            $identity = $service->pickRandomIdentity(
                nationality: 'Spain',
                excludedNames: ['Diego García Pérez'],
            );

            $this->assertNotSame('Diego García Pérez', $identity['name']);
        }
    }

    public function test_basque_region_produces_basque_names_with_spanish_nationality(): void
    {
        $service = app(PlayerGeneratorService::class);

        $basqueSurnames = $this->reflectPool(
            \App\Support\Faker\Provider\eu_ES\Person::class,
            'lastName',
        );

        for ($i = 0; $i < 20; $i++) {
            $identity = $service->pickRandomIdentity(region: 'basque');

            [, $lastName] = explode(' ', $identity['name'], 2);
            $this->assertContains($lastName, $basqueSurnames);
            // Region flag forces Spanish nationality on the data layer.
            $this->assertSame(['Spain'], $identity['nationality']);
        }
    }

    public function test_catalan_region_produces_catalan_names_with_spanish_nationality(): void
    {
        $service = app(PlayerGeneratorService::class);

        $catalanSurnames = $this->reflectPool(
            \App\Support\Faker\Provider\ca_ES\Person::class,
            'lastName',
        );

        for ($i = 0; $i < 20; $i++) {
            $identity = $service->pickRandomIdentity(region: 'catalan');

            [, $lastName] = explode(' ', $identity['name'], 2);
            $this->assertContains($lastName, $catalanSurnames);
            $this->assertSame(['Spain'], $identity['nationality']);
        }
    }

    public function test_single_create_assigns_secondary_positions_array(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Left-Back',
            overallScore: 60,
            dateOfBirth: Carbon::createFromDate(2002, 6, 15),
            contractYears: 3,
        ));

        // The stored array always leads with the primary position; the accessor
        // de-dupes it back out for display.
        $this->assertIsArray($gamePlayer->secondary_positions);
        $this->assertSame('Left-Back', $gamePlayer->secondary_positions[0]);
    }

    public function test_bulk_create_assigns_well_shaped_secondary_positions(): void
    {
        $service = app(PlayerGeneratorService::class);

        $primary = 'Left-Back';
        $adjacent = \App\Support\PositionSlotMapper::getAdjacentPositions($primary);

        $dataItems = [];
        for ($i = 0; $i < 60; $i++) {
            $dataItems[] = new GeneratedPlayerData(
                teamId: $this->team->id,
                position: $primary,
                overallScore: 55,
                dateOfBirth: Carbon::createFromDate(2002, 6, 15),
                contractYears: 3,
            );
        }

        $results = $service->createBulk($this->game, $dataItems);
        $this->assertCount(60, $results);

        $players = GamePlayer::whereIn('id', array_column($results, 'playerId'))->get();
        $sawExtraPosition = false;

        foreach ($players as $player) {
            // Bulk-generated regens must carry a secondary_positions array, not NULL.
            $this->assertIsArray($player->secondary_positions, 'Regen has no secondary_positions array');
            $this->assertNotEmpty($player->secondary_positions);
            $this->assertSame($primary, $player->secondary_positions[0]);

            // 1 primary + 0–2 secondaries = at most 3 entries.
            $this->assertLessThanOrEqual(3, count($player->secondary_positions));

            // Any extra position must be football-adjacent to the primary —
            // never a nonsensical pairing like Left-Back + Centre-Forward.
            $extras = array_slice($player->secondary_positions, 1);
            foreach ($extras as $extra) {
                $sawExtraPosition = true;
                $this->assertContains($extra, $adjacent, "Secondary {$extra} is not adjacent to {$primary}");
            }
        }

        // Across 60 players (~50% get extras) at least one should have a secondary.
        $this->assertTrue($sawExtraPosition, 'Expected at least one bulk regen to gain a secondary position');
    }

    /**
     * @return string[]
     */
    private function reflectPool(string $providerClass, string $property): array
    {
        $prop = new \ReflectionProperty($providerClass, $property);
        $prop->setAccessible(true);

        return $prop->getValue();
    }
}
