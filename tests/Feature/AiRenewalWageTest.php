<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI clubs used to renew contracts (RollAIContractRenewals + the season-end
 * ContractExpirationProcessor) by only extending contract_until, leaving
 * annual_wage frozen at the seeded rookie value for a player's whole career —
 * the other half of the wage asymmetry (a rival star earned a pittance while
 * the user's stars demanded fortunes). These cover re-deriving AI wages from
 * current market value at renewal time, in both directions.
 */
class AiRenewalWageTest extends TestCase
{
    use RefreshDatabase;

    private ContractService $contractService;
    private Game $game;
    private Team $userTeam;
    private Team $aiTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contractService = app(ContractService::class);

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create();
        $this->aiTeam = Team::factory()->create();

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'user_id' => $user->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    public function test_renew_ai_contracts_recomputes_wage_both_ways(): void
    {
        // Underpaid: seeded cheap but now worth €50M → wage should rise.
        $underpaid = GamePlayer::factory()->forGame($this->game)->forTeam($this->aiTeam)->create([
            'date_of_birth' => '1996-01-01', // ~30, prime
            'market_value_cents' => 5_000_000_000, // €50M
            'annual_wage' => 10_000_000,           // €100K
            'contract_until' => '2027-06-30',
        ]);
        // Overpaid: a faded star on €5M but now worth only €1M → wage should fall.
        $overpaid = GamePlayer::factory()->forGame($this->game)->forTeam($this->aiTeam)->create([
            'date_of_birth' => '1996-01-01',
            'market_value_cents' => 100_000_000, // €1M
            'annual_wage' => 500_000_000,        // €5M
            'contract_until' => '2027-06-30',
        ]);

        $this->contractService->renewAiContracts(
            $this->rowsFor($underpaid, $overpaid),
            '2030-06-30',
            '2026-08-15',
        );

        $underpaid->refresh();
        $overpaid->refresh();

        $this->assertSame('2030-06-30', $underpaid->contract_until->toDateString());
        $this->assertSame('2030-06-30', $overpaid->contract_until->toDateString());

        // €50M @ 15% ≈ €7.5M — far above the €100K seed.
        $this->assertGreaterThan(500_000_000, $underpaid->annual_wage, 'Underpaid star wage should rise toward market value');
        // €1M @ 8% ≈ €80K — well below the €5M legacy deal.
        $this->assertLessThan(500_000_000, $overpaid->annual_wage, 'Faded star wage should fall toward market value');
    }

    public function test_renew_ai_contracts_keeps_wage_when_market_value_missing(): void
    {
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($this->aiTeam)->create([
            'date_of_birth' => '1996-01-01',
            'market_value_cents' => 0,
            'annual_wage' => 500_000_000, // €5M
            'contract_until' => '2027-06-30',
        ]);

        $this->contractService->renewAiContracts($this->rowsFor($player), '2030-06-30', '2026-08-15');

        $player->refresh();
        $this->assertSame('2030-06-30', $player->contract_until->toDateString(), 'Contract still extends');
        $this->assertSame(500_000_000, $player->annual_wage, 'Wage is left untouched when there is no market value to price from');
    }

    public function test_contract_expiration_recomputes_ai_wages_and_leaves_user_team_alone(): void
    {
        // AI non-veteran with an expiring contract: auto-renewed AND re-priced.
        $aiPlayer = GamePlayer::factory()->forGame($this->game)->forTeam($this->aiTeam)->create([
            'date_of_birth' => '1996-01-01', // ~30, non-veteran
            'market_value_cents' => 3_000_000_000, // €30M
            'annual_wage' => 20_000_000,           // €200K, seeded cheap
            'contract_until' => '2027-06-30',
            'pending_annual_wage' => null,
        ]);

        // User-team player with an expiring contract: becomes a free agent, and
        // crucially his wage must NOT be recomputed by the AI renewal helper.
        $userPlayer = GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->create([
            'date_of_birth' => '1996-01-01',
            'market_value_cents' => 3_000_000_000,
            'annual_wage' => 20_000_000,
            'contract_until' => '2027-06-30',
            'pending_annual_wage' => null,
        ]);

        app(ContractExpirationProcessor::class)->process($this->game, new SeasonTransitionData(
            oldSeason: '2026',
            newSeason: '2027',
            competitionId: $this->game->competition_id,
        ));

        $aiPlayer->refresh();
        $userPlayer->refresh();

        // seasonYear (2026) + 3 = 2029.
        $this->assertSame('2029-06-30', $aiPlayer->contract_until->toDateString(), 'AI keeper is auto-renewed');
        $this->assertGreaterThan(20_000_000, $aiPlayer->annual_wage, 'AI renewal re-derives the wage from market value');

        $this->assertNull($userPlayer->team_id, 'Expiring user-team player becomes a free agent');
        $this->assertSame(20_000_000, $userPlayer->annual_wage, 'AI wage recompute must never touch the user team');
    }

    /**
     * Build the lightweight row objects renewAiContracts() consumes (mirrors the
     * raw DB::table select the production callers pass in).
     */
    private function rowsFor(GamePlayer ...$players): \Illuminate\Support\Collection
    {
        return collect($players)->map(fn (GamePlayer $p) => (object) [
            'id' => $p->id,
            'team_id' => $p->team_id,
            'market_value_cents' => $p->market_value_cents,
            'date_of_birth' => $p->date_of_birth->toDateString(),
        ]);
    }
}
