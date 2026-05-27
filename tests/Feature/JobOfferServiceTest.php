<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\User;
use App\Modules\Competition\Promotions\PromotionMove;
use App\Modules\Competition\Promotions\PromotionRelegationPlan;
use App\Modules\Competition\Promotions\PromotionRelegationQuery;
use App\Modules\Manager\Services\JobOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * Regression coverage for the Pro Manager loyalty bug: JobOfferService used
 * to read club reputation from ClubProfile (control-plane, static), which
 * meant a manager who grew their original team into a giant never saw their
 * club's growth reflected in the offer ladder. Every other system reads from
 * the per-game TeamReputation, so JobOfferService now does too.
 *
 * The tests exercise generateEndOfSeasonOffers() directly so the grade →
 * rank-shift plan and the candidate-pool filter are both validated without
 * depending on standings resolution.
 */
class JobOfferServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    /**
     * Loyalty regression: a manager whose ClubProfile is still `local`
     * (because the club was originally a Primera RFEF minnow) but whose
     * in-game TeamReputation has climbed to `elite` after years of league
     * wins must field tier-1 elite offers on an exceptional season — i.e.,
     * the Real Madrid-tier offers that the static ClubProfile read used to
     * make unreachable.
     */
    public function test_loyal_manager_with_in_game_elite_reputation_sees_top_tier_offers(): void
    {
        [$game] = $this->seedScenario(
            userClubProfileReputation: ClubProfile::REPUTATION_LOCAL,
            userTeamReputation: ClubProfile::REPUTATION_ELITE,
        );

        // Three rival tier-1 clubs whose in-game TeamReputation is elite —
        // the pool the loyal manager should now be drawing offers from.
        $this->seedRival($game, ClubProfile::REPUTATION_ELITE, ClubProfile::REPUTATION_ELITE);
        $this->seedRival($game, ClubProfile::REPUTATION_ELITE, ClubProfile::REPUTATION_ELITE);
        $this->seedRival($game, ClubProfile::REPUTATION_ELITE, ClubProfile::REPUTATION_ELITE);

        $offers = app(JobOfferService::class)
            ->generateEndOfSeasonOffers($game->refresh(), 'exceptional');

        $this->assertNotEmpty($offers, 'An exceptional season at an elite-rep club should produce at least one offer.');

        foreach ($offers as $offer) {
            $this->assertSame(
                ClubProfile::REPUTATION_ELITE,
                $offer->target_reputation_level,
                'Offers for a loyal elite-rep manager should land at tier-1 elite, not the stale ClubProfile tier.',
            );
            $this->assertSame(
                ClubProfile::REPUTATION_ELITE,
                $offer->source_reputation_level,
                'source_reputation_level must reflect the in-game TeamReputation, not the editorial ClubProfile seed.',
            );
        }
    }

    /**
     * Symmetric check on the rival side: a rival club whose ClubProfile is
     * `continental` but whose in-game TeamReputation has slipped to
     * `established` belongs in the established candidate pool, not the
     * continental one. Before the fix the static ClubProfile decided the
     * bucket; now TeamReputation does.
     */
    public function test_rival_in_game_decline_moves_them_into_the_lower_candidate_pool(): void
    {
        [$game] = $this->seedScenario(
            userClubProfileReputation: ClubProfile::REPUTATION_ESTABLISHED,
            userTeamReputation: ClubProfile::REPUTATION_ESTABLISHED,
        );

        // The "declining" rival: editorial seed says continental, in-game
        // truth says established. After the fix this team should be eligible
        // at the established tier-1 rank, not the continental one.
        $declinedRival = $this->seedRival(
            $game,
            clubProfileReputation: ClubProfile::REPUTATION_CONTINENTAL,
            teamReputation: ClubProfile::REPUTATION_ESTABLISHED,
        );

        // "exceeded" deterministically generates two offers: one at +1 rank
        // (tier-1 continental) and one at +0 rank (tier-1 established). With
        // the declined rival as the only candidate at the established slot,
        // it must appear in the result if the per-game filter is correct.
        $offers = app(JobOfferService::class)
            ->generateEndOfSeasonOffers($game->refresh(), 'exceeded');

        $this->assertTrue(
            $offers->contains(fn (ManagerJobOffer $offer) => $offer->team_id === $declinedRival->id),
            'A rival whose in-game TeamReputation matches the user\'s tier should appear in the lateral candidate pool, '
            . 'even when their editorial ClubProfile is one tier higher.',
        );

        foreach ($offers as $offer) {
            if ($offer->team_id === $declinedRival->id) {
                $this->assertSame(
                    ClubProfile::REPUTATION_ESTABLISHED,
                    $offer->target_reputation_level,
                    'The offer snapshot must record the rival\'s in-game tier, not the ClubProfile tier.',
                );
            }
        }
    }

    /**
     * League-label bug #1215: a rival whose CompetitionEntry already says
     * "second division" because the planner is about to promote them this
     * season must have that destination league stamped onto the offer — not
     * the league they finished the season in. Drives the card under their
     * name on /season-offers.
     */
    public function test_offer_stamp_uses_planner_destination_for_team_being_promoted_this_season(): void
    {
        [$game] = $this->seedScenario(
            userClubProfileReputation: ClubProfile::REPUTATION_ELITE,
            userTeamReputation: ClubProfile::REPUTATION_ELITE,
        );

        // Rival's seed (CompetitionTeam) sits them at tier-1 elite so they're
        // in the bucketing pool. Their per-game entry says ESP2 — the league
        // they actually played in this season after a prior relegation.
        $rival = $this->seedRivalInEntry(
            $game,
            clubProfileReputation: ClubProfile::REPUTATION_ELITE,
            teamReputation: ClubProfile::REPUTATION_ELITE,
            seedCompetitionId: 'ESP1',
            entryCompetitionId: 'ESP2',
        );

        $this->stubPlanWithMoves($game, [
            new PromotionMove(
                teamId: $rival->id,
                fromCompetitionId: 'ESP2',
                toCompetitionId: 'ESP1',
                reason: PromotionMove::REASON_PROMOTION,
            ),
        ]);

        $offers = app(JobOfferService::class)
            ->generateEndOfSeasonOffers($game->refresh(), 'exceptional');

        $rivalOffer = $offers->first(fn (ManagerJobOffer $o) => $o->team_id === $rival->id);
        $this->assertNotNull($rivalOffer, 'Rival should have been picked for a lateral elite offer.');
        $this->assertSame('ESP1', $rivalOffer->competition_id, 'Offer must be stamped with the planner destination (ESP1), not the league played this season (ESP2).');
    }

    /**
     * Symmetric case: a rival currently in ESP1 but about to be relegated at
     * the end of this season should have ESP2 stamped on the offer — not
     * ESP1, even though that's what their CompetitionEntry still says
     * (PromotionRelegationProcessor hasn't run yet at offer generation time).
     */
    public function test_offer_stamp_uses_planner_destination_for_team_being_relegated_this_season(): void
    {
        [$game] = $this->seedScenario(
            userClubProfileReputation: ClubProfile::REPUTATION_ELITE,
            userTeamReputation: ClubProfile::REPUTATION_ELITE,
        );

        // The planner stub below points the move at ESP2, which is the
        // competition_id that ends up on the offer row. seedRivalInEntry
        // only creates ESP2 when the *entry* lives there — here the entry
        // is ESP1, so we have to seed ESP2 explicitly to satisfy the
        // manager_job_offers FK.
        Competition::factory()->league()->create([
            'id' => 'ESP2',
            'name' => 'LaLiga 2',
            'country' => 'ES',
            'tier' => 2,
            'season' => $game->season,
        ]);

        $rival = $this->seedRivalInEntry(
            $game,
            clubProfileReputation: ClubProfile::REPUTATION_ELITE,
            teamReputation: ClubProfile::REPUTATION_ELITE,
            seedCompetitionId: 'ESP1',
            entryCompetitionId: 'ESP1',
        );

        $this->stubPlanWithMoves($game, [
            new PromotionMove(
                teamId: $rival->id,
                fromCompetitionId: 'ESP1',
                toCompetitionId: 'ESP2',
                reason: PromotionMove::REASON_RELEGATION,
            ),
        ]);

        $offers = app(JobOfferService::class)
            ->generateEndOfSeasonOffers($game->refresh(), 'exceptional');

        $rivalOffer = $offers->first(fn (ManagerJobOffer $o) => $o->team_id === $rival->id);
        $this->assertNotNull($rivalOffer);
        $this->assertSame('ESP2', $rivalOffer->competition_id, 'Offer must be stamped with the planner destination (ESP2), not the league still recorded on the rival CompetitionEntry (ESP1).');
    }

    /**
     * Baseline / regression guard: when the rival has no pending move and
     * their CompetitionEntry matches the static seed, the stamped
     * competition_id matches both. The fix must not regress the common case.
     */
    public function test_offer_stamp_matches_current_entry_when_no_pending_move(): void
    {
        [$game] = $this->seedScenario(
            userClubProfileReputation: ClubProfile::REPUTATION_ELITE,
            userTeamReputation: ClubProfile::REPUTATION_ELITE,
        );

        $rival = $this->seedRivalInEntry(
            $game,
            clubProfileReputation: ClubProfile::REPUTATION_ELITE,
            teamReputation: ClubProfile::REPUTATION_ELITE,
            seedCompetitionId: 'ESP1',
            entryCompetitionId: 'ESP1',
        );

        $this->stubPlanWithMoves($game, []);

        $offers = app(JobOfferService::class)
            ->generateEndOfSeasonOffers($game->refresh(), 'exceptional');

        $rivalOffer = $offers->first(fn (ManagerJobOffer $o) => $o->team_id === $rival->id);
        $this->assertNotNull($rivalOffer);
        $this->assertSame('ESP1', $rivalOffer->competition_id);
    }

    /**
     * Cross-country regression for #1215: when a foreign club's
     * CompetitionEntry has drifted from its editorial seed (e.g. they were
     * moved between leagues in a previous save-session that ran the engine
     * for their country, or the seed simply differs from the per-game state)
     * the offer card must reflect the entry, not the seed. Planner only
     * covers $game->country, so the entry-read path is what fires here.
     */
    public function test_offer_stamp_uses_current_entry_for_foreign_team_that_moved_leagues(): void
    {
        [$game] = $this->seedScenario(
            userClubProfileReputation: ClubProfile::REPUTATION_ELITE,
            userTeamReputation: ClubProfile::REPUTATION_ELITE,
        );

        // English tier-1 league exists separately so the foreign rival can sit
        // in its own country's pool without polluting Spain.
        Competition::factory()->league()->create([
            'id' => 'ENG1',
            'name' => 'Premier League',
            'country' => 'EN',
            'flag' => 'gb',
            'tier' => 1,
            'season' => $game->season,
        ]);
        // A second English league at a lower tier so the entry-vs-seed drift
        // is observable.
        Competition::factory()->league()->create([
            'id' => 'ENG2',
            'name' => 'Championship',
            'country' => 'EN',
            'flag' => 'gb',
            'tier' => 2,
            'season' => $game->season,
        ]);

        $rival = Team::factory()->create(['country' => 'EN']);

        ClubProfile::create([
            'team_id' => $rival->id,
            'reputation_level' => ClubProfile::REPUTATION_ELITE,
        ]);

        // Seed pivot: ENG1 (editorial home). Per-game entry: ENG2 (drifted).
        $rival->competitions()->attach('ENG1', ['season' => $game->season]);
        CompetitionEntry::create([
            'game_id' => $game->id,
            'competition_id' => 'ENG2',
            'team_id' => $rival->id,
            'entry_round' => 1,
        ]);

        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $rival->id,
            'reputation_level' => ClubProfile::REPUTATION_ELITE,
            'base_reputation_level' => ClubProfile::REPUTATION_ELITE,
            'reputation_points' => TeamReputation::pointsForTier(ClubProfile::REPUTATION_ELITE),
        ]);
        TeamReputation::flushCacheFor($game->id, $rival->id);

        // Planner only sees the user's country (ES); foreign team should not
        // appear in any move. Stub explicitly so the test doesn't depend on
        // the real snapshot builder, which would need full ES tier seeding.
        $this->stubPlanWithMoves($game, []);

        $offers = app(JobOfferService::class)
            ->generateEndOfSeasonOffers($game->refresh(), 'exceptional');

        $rivalOffer = $offers->first(fn (ManagerJobOffer $o) => $o->team_id === $rival->id);
        $this->assertNotNull($rivalOffer, 'Foreign elite rival should be eligible for a lateral elite offer.');
        $this->assertSame('ENG2', $rivalOffer->competition_id, 'Foreign rival should be stamped with their per-game CompetitionEntry (ENG2), not the seed (ENG1).');
    }

    /**
     * Replace the bound PromotionRelegationQuery with a stub whose
     * planForGame() returns a hand-built plan. Keeps tests deterministic and
     * isolated from the (much heavier) real snapshot builder, which would
     * require seeding every tier of the country to satisfy size assertions.
     *
     * @param array<int, PromotionMove> $moves
     */
    private function stubPlanWithMoves(Game $game, array $moves): void
    {
        $plan = new PromotionRelegationPlan(
            countryCode: $game->country ?? 'ES',
            moves: $moves,
        );

        $stub = Mockery::mock(PromotionRelegationQuery::class);
        $stub->shouldReceive('planForGame')->andReturn($plan);
        $stub->shouldReceive('wasTeamPromoted')->andReturn(false);
        $stub->shouldReceive('predictedCompetitionIdForTeam')->andReturnUsing(
            function (Game $g, string $teamId) use ($moves): ?string {
                foreach ($moves as $move) {
                    if ($move->teamId === $teamId) {
                        return $move->toCompetitionId;
                    }
                }
                return null;
            }
        );

        $this->app->instance(PromotionRelegationQuery::class, $stub);
    }

    /**
     * Variant of seedRival() that also creates the per-game CompetitionEntry
     * row needed by the offer-stamp resolver. The seed competition (via the
     * CompetitionTeam pivot) decides which bucket the rival lands in;
     * $entryCompetitionId decides what the resolver reads when no plan
     * override applies.
     */
    private function seedRivalInEntry(
        Game $game,
        string $clubProfileReputation,
        string $teamReputation,
        string $seedCompetitionId,
        string $entryCompetitionId,
    ): Team {
        // Make sure both competitions exist — seedScenario only seeds ESP1.
        if ($entryCompetitionId !== 'ESP1' && Competition::find($entryCompetitionId) === null) {
            Competition::factory()->league()->create([
                'id' => $entryCompetitionId,
                'name' => $entryCompetitionId,
                'country' => 'ES',
                'tier' => $entryCompetitionId === 'ESP2' ? 2 : 3,
                'season' => $game->season,
            ]);
        }
        if ($seedCompetitionId !== 'ESP1' && Competition::find($seedCompetitionId) === null) {
            Competition::factory()->league()->create([
                'id' => $seedCompetitionId,
                'name' => $seedCompetitionId,
                'country' => 'ES',
                'tier' => $seedCompetitionId === 'ESP2' ? 2 : 3,
                'season' => $game->season,
            ]);
        }

        $team = Team::factory()->create(['country' => 'ES']);

        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => $clubProfileReputation,
        ]);

        $team->competitions()->attach($seedCompetitionId, ['season' => $game->season]);

        CompetitionEntry::create([
            'game_id' => $game->id,
            'competition_id' => $entryCompetitionId,
            'team_id' => $team->id,
            'entry_round' => 1,
        ]);

        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => $teamReputation,
            'base_reputation_level' => $clubProfileReputation,
            'reputation_points' => TeamReputation::pointsForTier($teamReputation),
        ]);
        TeamReputation::flushCacheFor($game->id, $team->id);

        return $team;
    }

    /**
     * @return array{0: Game, 1: Team}
     */
    private function seedScenario(string $userClubProfileReputation, string $userTeamReputation): array
    {
        $season = '2025';

        $laLiga = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'country' => 'ES',
            'tier' => 1,
            'season' => $season,
        ]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team']);

        ClubProfile::create([
            'team_id' => $userTeam->id,
            'reputation_level' => $userClubProfileReputation,
        ]);

        $userTeam->competitions()->attach($laLiga->id, ['season' => $season]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $laLiga->id,
            'season' => $season,
            'game_mode' => Game::MODE_CAREER_PRO,
            'current_date' => '2026-05-23',
        ]);

        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
            'reputation_level' => $userTeamReputation,
            'base_reputation_level' => $userClubProfileReputation,
            'reputation_points' => TeamReputation::pointsForTier($userTeamReputation),
        ]);
        // resolveLevel memoizes per-process; clear so each test sees its own seed.
        TeamReputation::flushCacheFor($game->id, $userTeam->id);

        return [$game, $userTeam];
    }

    private function seedRival(Game $game, string $clubProfileReputation, string $teamReputation): Team
    {
        $team = Team::factory()->create();

        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => $clubProfileReputation,
        ]);

        $team->competitions()->attach('ESP1', ['season' => $game->season]);

        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => $teamReputation,
            'base_reputation_level' => $clubProfileReputation,
            'reputation_points' => TeamReputation::pointsForTier($teamReputation),
        ]);
        TeamReputation::flushCacheFor($game->id, $team->id);

        return $team;
    }
}
