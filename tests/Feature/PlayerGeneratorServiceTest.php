<?php

namespace Tests\Feature;

use App\Modules\Squad\DTOs\GeneratedPlayerData;
use App\Modules\Squad\Services\PlayerGeneratorService;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
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

    public function test_creates_player_and_game_player_records(): void
    {
        $service = app(PlayerGeneratorService::class);
        $dateOfBirth = Carbon::createFromDate(2002, 6, 15);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Centre-Back',
            technical: 65,
            physical: 70,
            dateOfBirth: $dateOfBirth,
            contractYears: 3,
        ));

        // GamePlayer record exists
        $this->assertDatabaseHas('game_players', ['id' => $gamePlayer->id]);
        $this->assertEquals('Centre-Back', $gamePlayer->position);
        $this->assertEquals($this->team->id, $gamePlayer->team_id);
        $this->assertEquals($this->game->id, $gamePlayer->game_id);
        $this->assertEquals(65, $gamePlayer->game_technical_ability);
        $this->assertEquals(70, $gamePlayer->game_physical_ability);
        // Player reference record exists
        $this->assertDatabaseHas('players', ['id' => $gamePlayer->player_id]);
        $player = Player::find($gamePlayer->player_id);
        $this->assertEquals(65, $player->technical_ability);
        $this->assertEquals(70, $player->physical_ability);
        $this->assertEquals($dateOfBirth->toDateString(), $player->date_of_birth->toDateString());

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
            technical: 55,
            physical: 50,
            dateOfBirth: Carbon::createFromDate(2006, 3, 10),
            contractYears: 3,
        ));

        $player = $gamePlayer->player;
        $this->assertNotEmpty($player->name);
        $this->assertNotEmpty($player->nationality);
        $this->assertIsArray($player->nationality);
    }

    public function test_uses_provided_name_and_nationality(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Centre-Forward',
            technical: 60,
            physical: 65,
            dateOfBirth: Carbon::createFromDate(2000, 1, 1),
            contractYears: 2,
            name: 'Test Player',
            nationality: ['BRA'],
        ));

        $player = $gamePlayer->player;
        $this->assertEquals('Test Player', $player->name);
        $this->assertEquals(['BRA'], $player->nationality);
    }

    public function test_auto_estimates_market_value(): void
    {
        $service = app(PlayerGeneratorService::class);

        $gamePlayer = $service->create($this->game, new GeneratedPlayerData(
            teamId: $this->team->id,
            position: 'Central Midfield',
            technical: 75,
            physical: 75,
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
            technical: 50,
            physical: 55,
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
            technical: 45,
            physical: 50,
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
            technical: 60,
            physical: 65,
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
            technical: 50,
            physical: 55,
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
            technical: 40,
            physical: 45,
            dateOfBirth: Carbon::createFromDate(2007, 7, 20),
            contractYears: 3,
        ));

        $this->assertNotNull($gamePlayer->durability);
        $this->assertGreaterThan(0, $gamePlayer->durability);
    }
}
