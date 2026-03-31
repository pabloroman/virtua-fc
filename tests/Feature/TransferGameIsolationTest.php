<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferGameIsolationTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $transferService;

    private LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = app(TransferService::class);
        $this->loanService = app(LoanService::class);
    }

    public function test_sign_free_agent_route_cannot_target_player_from_another_game(): void
    {
        [$user, $game] = $this->createOwnedGame();
        [, $otherGame] = $this->createOwnedGame();

        $freeAgent = GamePlayer::factory()->forGame($otherGame)->create([
            'team_id' => null,
        ]);

        $this->actingAs($user)
            ->post(route('game.scouting.sign-free-agent', [
                'gameId' => $game->id,
                'playerId' => $freeAgent->id,
            ]))
            ->assertNotFound();

        $this->assertDatabaseCount('transfer_offers', 0);
        $this->assertNull($freeAgent->fresh()->team_id);
    }

    public function test_request_loan_route_cannot_target_player_from_another_game(): void
    {
        [$user, $game] = $this->createOwnedGame();
        [, $otherGame] = $this->createOwnedGame();

        $loanTarget = GamePlayer::factory()->forGame($otherGame)->create();

        $this->actingAs($user)
            ->post(route('game.scouting.loan', [
                'gameId' => $game->id,
                'playerId' => $loanTarget->id,
            ]))
            ->assertNotFound();

        $this->assertDatabaseCount('transfer_offers', 0);
        $this->assertSame($otherGame->id, $loanTarget->fresh()->game_id);
    }

    public function test_pre_contract_route_cannot_target_player_from_another_game(): void
    {
        [$user, $game] = $this->createOwnedGame([
            'current_date' => '2025-01-15',
            'season' => '2025',
        ]);
        [, $otherGame] = $this->createOwnedGame([
            'current_date' => '2025-01-15',
            'season' => '2025',
        ]);

        $expiringPlayer = GamePlayer::factory()->forGame($otherGame)->create([
            'contract_until' => '2025-06-30',
        ]);

        $this->actingAs($user)
            ->post(route('game.scouting.pre-contract', [
                'gameId' => $game->id,
                'playerId' => $expiringPlayer->id,
            ]), [
                'offered_wage' => 150000,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('transfer_offers', 0);
        $this->assertSame($otherGame->id, $expiringPlayer->fresh()->game_id);
    }

    public function test_sign_free_agent_service_rejects_player_from_another_game(): void
    {
        [, $game] = $this->createOwnedGame([
            'current_date' => '2025-01-15',
            'season' => '2025',
        ]);
        [, $otherGame] = $this->createOwnedGame([
            'current_date' => '2025-01-15',
            'season' => '2025',
        ]);

        $freeAgent = GamePlayer::factory()->forGame($otherGame)->create([
            'team_id' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Player does not belong to this game.');
        $this->transferService->signFreeAgent($game, $freeAgent, 100000);
    }

    public function test_submit_pre_contract_service_rejects_player_from_another_game(): void
    {
        [, $game] = $this->createOwnedGame([
            'current_date' => '2025-01-15',
            'season' => '2025',
        ]);
        [, $otherGame] = $this->createOwnedGame([
            'current_date' => '2025-01-15',
            'season' => '2025',
        ]);

        $player = GamePlayer::factory()->forGame($otherGame)->create([
            'contract_until' => '2025-06-30',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Player does not belong to this game.');
        $this->transferService->submitPreContractOffer($game, $player, 100000);
    }

    public function test_request_loan_service_rejects_player_from_another_game(): void
    {
        [, $game] = $this->createOwnedGame();
        [, $otherGame] = $this->createOwnedGame();

        $player = GamePlayer::factory()->forGame($otherGame)->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Player does not belong to this game.');
        $this->loanService->requestLoanIn($game, $player);
    }

    private function createOwnedGame(array $attributes = []): array
    {
        $competition = Competition::factory()->league()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $game = Game::factory()->create(array_merge([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $competition->id,
        ], $attributes));

        return [$user, $game];
    }
}
