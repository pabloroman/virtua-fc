<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The salary cap must block a free-agent signing that would push the wage bill
 * over the cap — even when the club is sitting on a mountain of transfer cash
 * (the exploit). Once wages are freed up, the same signing must go through.
 */
class SalaryCapEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Competition $competition;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('finances.wage_cap_ratio', 0.70);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'tier' => 1,
        ]);
    }

    public function test_free_agent_signing_blocked_at_cap_despite_hoarded_cash(): void
    {
        // Revenue €10M → cap €7M. Squad already sits exactly at the cap.
        [$user, $game] = $this->makeGame(projectedRevenue: 1_000_000_000, squadWage: 700_000_000);

        // Pile on one-time cash: it must NOT buy any wage headroom.
        GameInvestment::create([
            'game_id' => $game->id,
            'season' => $game->season,
            'transfer_budget' => 50_000_000_000, // €500M
        ]);

        $freeAgent = $this->freeAgent($game);

        $response = $this->actingAs($user)->post(
            route('game.scouting.sign-free-agent', [$game->id, $freeAgent->id])
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('transfer_offers', [
            'game_id' => $game->id,
            'game_player_id' => $freeAgent->id,
        ]);
    }

    public function test_free_agent_signing_allowed_once_wages_are_freed(): void
    {
        // Revenue €10M → cap €7M, but the squad only commits €1M → €6M of room.
        [$user, $game] = $this->makeGame(projectedRevenue: 1_000_000_000, squadWage: 100_000_000);

        $freeAgent = $this->freeAgent($game);

        $response = $this->actingAs($user)->post(
            route('game.scouting.sign-free-agent', [$game->id, $freeAgent->id])
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('transfer_offers', [
            'game_id' => $game->id,
            'game_player_id' => $freeAgent->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_AGREED,
        ]);
    }

    public function test_over_cap_locks_signing_with_the_locked_message(): void
    {
        // Revenue €10M → cap €7M, but the squad already commits €8M → over cap.
        [$user, $game] = $this->makeGame(projectedRevenue: 1_000_000_000, squadWage: 800_000_000);

        $freeAgent = $this->freeAgent($game);

        $response = $this->actingAs($user)->post(
            route('game.scouting.sign-free-agent', [$game->id, $freeAgent->id])
        );

        $response->assertRedirect();
        $response->assertSessionHas('error', __('messages.salary_cap_locked'));

        $this->assertDatabaseMissing('transfer_offers', [
            'game_id' => $game->id,
            'game_player_id' => $freeAgent->id,
        ]);
    }

    public function test_market_unlocks_once_the_bill_is_back_under_the_cap(): void
    {
        // Squad starts over the cap (€8M vs €7M cap).
        [$user, $game] = $this->makeGame(projectedRevenue: 1_000_000_000, squadWage: 800_000_000);

        // Simulate selling the high earner down to €1M — the bill is now under cap.
        GamePlayer::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->update(['annual_wage' => 100_000_000]);

        $freeAgent = $this->freeAgent($game);

        $response = $this->actingAs($user)->post(
            route('game.scouting.sign-free-agent', [$game->id, $freeAgent->id])
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('transfer_offers', [
            'game_id' => $game->id,
            'game_player_id' => $freeAgent->id,
            'status' => TransferOffer::STATUS_AGREED,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @return array{0: User, 1: Game}
     */
    private function makeGame(int $projectedRevenue, int $squadWage): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        ClubProfile::create(['team_id' => $team->id, 'reputation_level' => ClubProfile::REPUTATION_ESTABLISHED]);
        $team->competitions()->attach($this->competition->id, ['season' => '2024']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2025-01-15',
        ]);

        GameFinances::create([
            'game_id' => $game->id,
            'season' => '2024',
            'projected_total_revenue' => $projectedRevenue,
            'projected_wages' => $squadWage,
        ]);

        // A single squad player carrying the whole wage bill.
        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'annual_wage' => $squadWage,
        ]);

        return [$user, $game];
    }

    private function freeAgent(Game $game): GamePlayer
    {
        // Tier 1 (<€1M) only requires LOCAL reputation, so an established club
        // is always a willing destination and the reputation gate passes —
        // isolating the salary cap as the only thing that can block the deal.
        // (The factory derives `tier` from a random value, so pin it explicitly.)
        //
        // Pin `overall_score` too: the factory randomises it (40–90) and the
        // wage demand is computed deterministically off ability, so an unpinned
        // overall makes the demand swing wildly and can intermittently breach
        // the modest (€6M) cap room the "allowed" cases rely on. A low ability
        // keeps the demand comfortably under that room.
        return GamePlayer::factory()->age(26)->create([
            'game_id' => $game->id,
            'team_id' => null,
            'market_value_cents' => 20_000_000, // €200K
            'tier' => 1,
            'overall_score' => 50,
        ]);
    }
}
