<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI-to-AI transfers bypass TransferCompletionService, so the clause recompute
 * lives in AITransferMarketService::prepareTransfer instead. These tests drive
 * that method (and the real batched upsert) directly to prove a player's clause
 * is re-anchored to the buying club's country: cleared when a Spanish player
 * moves abroad, re-derived to the ES floor on an intra-Spain move, and left null
 * when the feature is off.
 */
class ReleaseClauseAITransferTest extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;      // €50M
    private const CLAUSE = 6_250_000_000;  // €50M × 1.25 = €62.5M (stale ES clause)

    private AITransferMarketService $service;
    private ContractService $contractService;
    private Game $game;
    private Team $spanishSeller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AITransferMarketService::class);
        $this->contractService = app(ContractService::class);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);
        $this->spanishSeller = Team::factory()->create(['name' => 'Spanish Seller', 'country' => 'ES']);

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'current_date' => '2025-08-01',
            'release_clauses_enabled' => true,
        ]);
    }

    public function test_clause_is_cleared_when_a_spanish_player_moves_to_a_foreign_club(): void
    {
        $foreignBuyer = Team::factory()->create(['name' => 'English Buyer', 'country' => 'EN']);
        $player = $this->sellerPlayerWithClause();

        $this->runAiTransfer($player, $foreignBuyer, 'EN');

        $player->refresh();
        $this->assertSame($foreignBuyer->id, $player->team_id);
        $this->assertNull($player->release_clause, 'A foreign (non-ES) buyer carries no clause.');
    }

    public function test_clause_is_re_anchored_to_the_floor_on_an_intra_spain_move(): void
    {
        $spanishBuyer = Team::factory()->create(['name' => 'Spanish Buyer', 'country' => 'ES']);
        $player = $this->sellerPlayerWithClause(clause: null);

        $this->runAiTransfer($player, $spanishBuyer, 'ES');

        $expected = $this->contractService->calculateReleaseClause(self::MV, null, null, 'ES');

        $player->refresh();
        $this->assertSame($spanishBuyer->id, $player->team_id);
        $this->assertSame($expected, (int) $player->release_clause, 'An ES buyer re-derives the mandatory floor.');
    }

    public function test_clause_stays_null_when_the_feature_is_disabled(): void
    {
        $this->game->update(['release_clauses_enabled' => false]);
        $spanishBuyer = Team::factory()->create(['name' => 'Spanish Buyer', 'country' => 'ES']);
        $player = $this->sellerPlayerWithClause();

        $this->runAiTransfer($player, $spanishBuyer, 'ES');

        $player->refresh();
        $this->assertNull($player->release_clause, 'Flag-off saves never carry a clause, even for an ES buyer.');
    }

    private function sellerPlayerWithClause(?int $clause = self::CLAUSE): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($this->spanishSeller)->create([
            'market_value_cents' => self::MV,
            'release_clause' => $clause,
        ]);
    }

    /**
     * Invoke the private prepareTransfer + flushBatchedOperations pair the way
     * processAITransfers does, so the recompute runs through the real upsert.
     */
    private function runAiTransfer(GamePlayer $player, Team $buyer, string $toCountry): void
    {
        $playerUpdates = [];
        $transferInserts = [];

        $prepare = new \ReflectionMethod(AITransferMarketService::class, 'prepareTransfer');
        $prepare->setAccessible(true);
        $prepare->invokeArgs($this->service, [
            $this->game, $player, $this->spanishSeller->id, $buyer->id, 'summer', null,
            &$playerUpdates, &$transferInserts, 2, 3, 4, $toCountry,
        ]);

        $flush = new \ReflectionMethod(AITransferMarketService::class, 'flushBatchedOperations');
        $flush->setAccessible(true);
        $flush->invoke($this->service, $playerUpdates, $transferInserts);
    }
}
