<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Services\CareerActionProcessor;
use App\Modules\Squad\Services\SquadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards against nagging the user with "contract expiring" reminders for
 * players that have already announced their retirement — those contracts
 * cannot be renewed or sold, so a notification is just noise the user can't
 * act on.
 */
class RetiringPlayerExpiringContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $userTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2024',
            'current_date' => '2025-02-15',
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => '2024',
            'transfer_budget' => 50_000_000_00,
            'scouting_tier' => 1,
        ]);
        GameFinances::create([
            'game_id' => $this->game->id,
            'season' => '2024',
            'projected_revenue' => 100_000_000_00,
            'projected_wages' => 50_000_000_00,
            'projected_position' => 10,
        ]);
    }

    public function test_check_expiring_contracts_skips_retiring_players(): void
    {
        $active = $this->createExpiringPlayer(age: 28);
        $retiring = $this->createExpiringPlayer(age: 36, retiringAtSeason: '2024');

        $this->invokeCheckExpiringContracts();

        $this->assertDatabaseHas('game_notifications', [
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_CONTRACT_EXPIRING,
        ]);

        $notifiedPlayerIds = GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_CONTRACT_EXPIRING)
            ->get()
            ->pluck('metadata.player_id')
            ->all();

        $this->assertContains($active->id, $notifiedPlayerIds,
            'Non-retiring players with expiring contracts must still be notified'
        );
        $this->assertNotContains($retiring->id, $notifiedPlayerIds,
            'Retiring players must not trigger contract-expiring notifications'
        );
    }

    public function test_squad_overview_watchlist_excludes_retiring_players(): void
    {
        $active = $this->createExpiringPlayer(age: 27);
        $retiring = $this->createExpiringPlayer(age: 37, retiringAtSeason: '2024');

        $overview = app(SquadService::class)->buildSquadOverview($this->game->refresh());

        $watchlistIds = $overview['expiringThisSeason']->pluck('id')->all();

        $this->assertContains($active->id, $watchlistIds);
        $this->assertNotContains($retiring->id, $watchlistIds,
            'Retiring players must not appear in the squad sidebar contract watchlist'
        );
    }

    // =====================================================
    // Helpers
    // =====================================================

    private function createExpiringPlayer(int $age, ?string $retiringAtSeason = null): GamePlayer
    {
        $player = Player::factory()->age($age)->create([
            'technical_ability' => 65,
            'physical_ability' => 65,
        ]);

        return GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'player_id' => $player->id,
            'team_id' => $this->userTeam->id,
            'position' => 'Central Midfield',
            // Contract ends at the end of the current season.
            'contract_until' => '2025-06-30',
            'annual_wage' => 200_000_00,
            'retiring_at_season' => $retiringAtSeason,
        ]);
    }

    private function invokeCheckExpiringContracts(): void
    {
        $processor = app(CareerActionProcessor::class);
        $method = new ReflectionMethod($processor, 'checkExpiringContracts');
        $method->setAccessible(true);
        $method->invoke($processor, $this->game->refresh());
    }
}
