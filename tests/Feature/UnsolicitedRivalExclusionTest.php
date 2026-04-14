<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnsolicitedRivalExclusionTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = app(TransferService::class);
    }

    public function test_rival_blocked_from_generating_unsolicited_offers(): void
    {
        [$game] = $this->createScenario(
            userReputation: ClubProfile::REPUTATION_ELITE,
            aiCompetitionId: 'ESP1',
            aiReputation: ClubProfile::REPUTATION_ELITE,
        );

        // Rival is the ONLY eligible buyer — the filter removes it, so no
        // unsolicited offer can ever be created regardless of rand() outcome.
        for ($i = 0; $i < 50; $i++) {
            $this->transferService->generateUnsolicitedOffers($game);
        }

        $this->assertSame(
            0,
            TransferOffer::where('game_id', $game->id)
                ->where('offer_type', TransferOffer::TYPE_UNSOLICITED)
                ->count(),
            'Same-league + same-reputation rival must not create unsolicited offers.',
        );
    }

    public function test_same_league_different_reputation_still_generates_unsolicited_offers(): void
    {
        [$game] = $this->createScenario(
            userReputation: ClubProfile::REPUTATION_ELITE,
            aiCompetitionId: 'ESP1',
            aiReputation: ClubProfile::REPUTATION_CONTINENTAL,
        );

        $this->runUntilOfferOrLimit(
            fn () => $this->transferService->generateUnsolicitedOffers($game),
            $game,
            TransferOffer::TYPE_UNSOLICITED,
        );

        $this->assertGreaterThan(
            0,
            TransferOffer::where('game_id', $game->id)
                ->where('offer_type', TransferOffer::TYPE_UNSOLICITED)
                ->count(),
            'Same-league + different-reputation team should still be eligible.',
        );
    }

    public function test_different_league_same_reputation_still_generates_unsolicited_offers(): void
    {
        [$game] = $this->createScenario(
            userReputation: ClubProfile::REPUTATION_ELITE,
            aiCompetitionId: 'ENG1',
            aiReputation: ClubProfile::REPUTATION_ELITE,
        );

        $this->runUntilOfferOrLimit(
            fn () => $this->transferService->generateUnsolicitedOffers($game),
            $game,
            TransferOffer::TYPE_UNSOLICITED,
        );

        $this->assertGreaterThan(
            0,
            TransferOffer::where('game_id', $game->id)
                ->where('offer_type', TransferOffer::TYPE_UNSOLICITED)
                ->count(),
            'Different-league + same-reputation team should still be eligible.',
        );
    }

    public function test_lower_reputation_buyers_can_pursue_mid_tier_players(): void
    {
        // An ELITE team's €8M rotation player (tier 3) is the kind of
        // player a MODEST club can realistically afford. Before tier-scaled
        // chances, only the top-5 by value were visible to the AI so these
        // players were effectively invisible in the transfer market.
        [$game] = $this->createScenario(
            userReputation: ClubProfile::REPUTATION_ELITE,
            aiCompetitionId: 'ESP2',
            aiReputation: ClubProfile::REPUTATION_MODEST,
            playerValueCents: 800_000_000, // €8M, tier 3 (2% per matchday)
        );

        $this->runUntilOfferOrLimit(
            fn () => $this->transferService->generateUnsolicitedOffers($game),
            $game,
            TransferOffer::TYPE_UNSOLICITED,
            maxIterations: 1500,
        );

        $this->assertGreaterThan(
            0,
            TransferOffer::where('game_id', $game->id)
                ->where('offer_type', TransferOffer::TYPE_UNSOLICITED)
                ->count(),
            'Mid-tier players must be reachable by lower-reputation clubs under the tier-scaled chance.',
        );
    }

    public function test_rival_still_allowed_to_bid_on_listed_players(): void
    {
        [$game, $player] = $this->createScenario(
            userReputation: ClubProfile::REPUTATION_ELITE,
            aiCompetitionId: 'ESP1',
            aiReputation: ClubProfile::REPUTATION_ELITE,
        );

        // User opens the door by listing the player — rivals are free to bid here.
        $this->transferService->listPlayer($player);

        $this->runUntilOfferOrLimit(
            fn () => $this->transferService->generateOffersForListedPlayers($game),
            $game,
            TransferOffer::TYPE_LISTED,
        );

        $this->assertGreaterThan(
            0,
            TransferOffer::where('game_id', $game->id)
                ->where('offer_type', TransferOffer::TYPE_LISTED)
                ->count(),
            'Rival filter must not apply to listed-player offers.',
        );
    }

    /**
     * Create a minimal scenario with a user team and a single AI team so the
     * rival filter's effect is unambiguous (any offer that gets created must
     * come from that AI team).
     *
     * @return array{0: Game, 1: GamePlayer}
     */
    private function createScenario(
        string $userReputation,
        string $aiCompetitionId,
        string $aiReputation,
        int $playerValueCents = 3_000_000_000,
    ): array {
        $season = '2025';

        // User's league is always La Liga (ESP1) in these scenarios.
        $laLiga = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'country' => 'ES',
            'tier' => 1,
            'season' => $season,
        ]);

        // AI team's league — same row if they're in La Liga, otherwise a
        // separate domestic league (e.g., Premier League).
        $aiLeague = $aiCompetitionId === 'ESP1'
            ? $laLiga
            : Competition::factory()->league()->create([
                'id' => $aiCompetitionId,
                'name' => $aiCompetitionId . ' League',
                'country' => $aiCompetitionId === 'ENG1' ? 'EN' : 'XX',
                'tier' => 1,
                'season' => $season,
            ]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team']);
        $aiTeam = Team::factory()->create(['name' => 'AI Team']);

        ClubProfile::create(['team_id' => $userTeam->id, 'reputation_level' => $userReputation]);
        ClubProfile::create(['team_id' => $aiTeam->id, 'reputation_level' => $aiReputation]);

        $userTeam->competitions()->attach($laLiga->id, ['season' => $season]);
        $aiTeam->competitions()->attach($aiLeague->id, ['season' => $season]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $laLiga->id,
            'season' => $season,
            'current_date' => '2025-08-01',
        ]);

        // Seed reputations so TeamReputation::resolveLevel matches the intent
        // even if/when the model resolves from the game-scoped table first.
        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
            'reputation_level' => $userReputation,
            'base_reputation_level' => $userReputation,
            'reputation_points' => TeamReputation::pointsForTier($userReputation),
        ]);
        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $aiTeam->id,
            'reputation_level' => $aiReputation,
            'base_reputation_level' => $aiReputation,
            'reputation_points' => TeamReputation::pointsForTier($aiReputation),
        ]);

        // Player for the user — market value determines their tier, which in
        // turn gates which buyer reputations are interested (see
        // MIN_TIER_BY_REPUTATION) and the per-matchday chance of an offer
        // (see UNSOLICITED_OFFER_CHANCE_BY_TIER).
        $player = GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
            'market_value_cents' => $playerValueCents,
            'contract_until' => '2027-06-30',
        ]);

        // Give the AI team a fat squad so the 15% fee-to-squad-value ratio is
        // never the reason an offer fails to materialise (€400M total).
        for ($i = 0; $i < 10; $i++) {
            GamePlayer::factory()->create([
                'game_id' => $game->id,
                'team_id' => $aiTeam->id,
                'market_value_cents' => 4_000_000_000, // €40M each
            ]);
        }

        return [$game, $player];
    }

    /**
     * Call the generator repeatedly until at least one offer of the given type
     * exists, or we hit the iteration ceiling. The default ceiling is sized
     * so that at the lowest realistic per-iteration odds (~2% for tier-3
     * players) the probability of a false-negative is vanishingly small —
     * a failure therefore points to a real regression, not RNG unluckiness.
     */
    private function runUntilOfferOrLimit(callable $generator, Game $game, string $offerType, int $maxIterations = 500): void
    {
        for ($i = 0; $i < $maxIterations; $i++) {
            $generator();

            $exists = TransferOffer::where('game_id', $game->id)
                ->where('offer_type', $offerType)
                ->exists();

            if ($exists) {
                return;
            }
        }
    }
}
