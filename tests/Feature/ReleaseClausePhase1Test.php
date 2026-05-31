<?php

namespace Tests\Feature;

use App\Models\Competition;
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
 * Phase-1 behaviour: the feature flag gates every clause write, the mandatory
 * ES clause refreshes at contract touchpoints, and the clause is nulled when a
 * player's contract expires. The "golden handcuffs" maths itself lives in
 * {@see \Tests\Unit\ReleaseClauseCalculationTest}.
 */
class ReleaseClausePhase1Test extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;        // €50M
    private const ES_FLOOR = 6_250_000_000;  // €50M × 1.25

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('finances.release_clause.es_floor_multiplier', 1.25);
        Competition::factory()->league()->create(['id' => 'ESP1']);
    }

    public function test_existing_saves_have_release_clauses_disabled_by_default(): void
    {
        // No flag passed → the migration default (false) applies, so existing
        // saves never see the feature.
        $game = Game::factory()->create();

        $this->assertFalse($game->fresh()->release_clauses_enabled);
    }

    public function test_renewal_sets_the_es_floor_clause_when_enabled(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->squadPlayer($game, $team);

        $renewed = app(ContractService::class)->processRenewal($player, 6_000_000_00, 3);

        $this->assertTrue($renewed);
        $this->assertSame(self::ES_FLOOR, $player->fresh()->release_clause);
    }

    public function test_renewal_leaves_the_clause_null_when_feature_disabled(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: false);
        $player = $this->squadPlayer($game, $team);

        app(ContractService::class)->processRenewal($player, 6_000_000_00, 3);

        $this->assertNull($player->fresh()->release_clause);
    }

    public function test_renewal_leaves_the_clause_null_for_non_es_clubs(): void
    {
        [$game, $team] = $this->makeGame(country: 'EN', enabled: true);
        $player = $this->squadPlayer($game, $team);

        app(ContractService::class)->processRenewal($player, 6_000_000_00, 3);

        $this->assertNull($player->fresh()->release_clause);
    }

    public function test_contract_expiry_nulls_the_release_clause(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => self::MV,
            'release_clause' => self::ES_FLOOR,
            'pending_annual_wage' => null,
            // Contract ends at the close of the ending season.
            'contract_until' => '2025-06-30',
        ]);

        app(ContractExpirationProcessor::class)->process($game, new SeasonTransitionData(
            oldSeason: '2024',
            newSeason: '2025',
            competitionId: $game->competition_id,
        ));

        $player->refresh();
        $this->assertNull($player->team_id, 'Player should have become a free agent');
        $this->assertNull($player->release_clause, 'A free agent carries no clause');
    }

    public function test_model_helpers_are_null_safe(): void
    {
        $with = GamePlayer::factory()->create(['release_clause' => self::ES_FLOOR]);
        $without = GamePlayer::factory()->create(['release_clause' => null]);

        $this->assertTrue($with->hasReleaseClause());
        $this->assertFalse($without->hasReleaseClause());

        // The accessor must return null (not throw) when there is no clause —
        // Money::format has a strict int hint.
        $this->assertNull($without->formatted_release_clause);
        $this->assertNotNull($with->formatted_release_clause);
        $this->assertStringContainsString('62.5', $with->formatted_release_clause);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @return array{0: Game, 1: Team}
     */
    private function makeGame(string $country, bool $enabled): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => $country]);

        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'competition_id' => 'ESP1',
            'country' => $country,
            'season' => '2024',
            'current_date' => '2025-02-15',
            'release_clauses_enabled' => $enabled,
        ]);

        return [$game, $team];
    }

    private function squadPlayer(Game $game, Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => self::MV,
            'release_clause' => null,
            'pending_annual_wage' => null,
            'contract_until' => '2026-06-30',
        ]);
    }
}
