<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase-3 behaviour: an AI club triggers the release clause on one of the user's
 * players. The forced, non-consensual sale is created straight at STATUS_AGREED
 * (outgoing), gates affordability on the clause (not market value), respects the
 * usual skips/exclusivity, and announces itself up front via a CRITICAL
 * notification (which also suppresses the generic completion notice).
 */
class ReleaseClausePhase3Test extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;      // €50M
    private const CLAUSE = 6_250_000_000;  // €50M × 1.25 = €62.5M
    private const BUDGET = 10_000_000_000; // €100M
    private const PLAYER_TIER = 5;

    private TransferService $transferService;
    private NotificationService $notificationService;
    private Game $game;
    private Team $userTeam;
    private Team $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = app(TransferService::class);
        $this->notificationService = app(NotificationService::class);

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);
        $this->buyer = Team::factory()->create(['name' => 'Rich AI Club', 'country' => 'EN']);

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'country' => 'ES',
            'current_date' => '2025-08-01',
            'release_clauses_enabled' => true,
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => $this->game->season,
            'transfer_budget' => self::BUDGET,
            'scouting_tier' => 1,
        ]);

        // Always fire the rarity roll for the player's tier so generation is
        // deterministic; eligibility is what each test actually exercises.
        config(['finances.release_clause.ai_trigger_chance_by_tier' => [self::PLAYER_TIER => 1.0]]);
    }

    public function test_generates_an_agreed_outgoing_clause_offer(): void
    {
        $player = $this->userPlayerWithClause();

        $offers = $this->transferService->generateAIReleaseClauseTriggers(
            $this->game,
            $this->buyerPool(squadValueCents: 50_000_000_000), // €500M → affords the clause
        );

        $this->assertCount(1, $offers);
        $offer = $offers->first();

        $this->assertSame(TransferOffer::STATUS_AGREED, $offer->status);
        $this->assertSame(TransferOffer::DIRECTION_OUTGOING, $offer->direction);
        $this->assertSame(TransferOffer::TYPE_UNSOLICITED, $offer->offer_type);
        $this->assertTrue((bool) $offer->triggered_release_clause);
        $this->assertSame(self::CLAUSE, (int) $offer->transfer_fee);
        $this->assertSame($this->userTeam->id, $offer->selling_team_id);
        $this->assertSame($this->buyer->id, $offer->offering_team_id);
        $this->assertSame($player->id, $offer->game_player_id);
    }

    public function test_affordability_gates_on_the_clause_not_market_value(): void
    {
        $this->userPlayerWithClause();

        // €400M squad → 15% = €60M: affords the €50M market value but NOT the
        // €62.5M clause, so the club must be excluded.
        $offers = $this->transferService->generateAIReleaseClauseTriggers(
            $this->game,
            $this->buyerPool(squadValueCents: 40_000_000_000),
        );

        $this->assertCount(0, $offers);
    }

    public function test_skips_players_that_already_have_a_live_offer(): void
    {
        $player = $this->userPlayerWithClause();

        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->buyer->id,
            'selling_team_id' => $this->userTeam->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => self::MV,
            'status' => TransferOffer::STATUS_PENDING,
            'negotiation_round' => 1,
            'expires_at' => '2025-08-31',
            'game_date' => '2025-08-01',
        ]);

        $offers = $this->transferService->generateAIReleaseClauseTriggers(
            $this->game,
            $this->buyerPool(squadValueCents: 50_000_000_000),
        );

        $this->assertCount(0, $offers);
    }

    public function test_skips_when_the_feature_is_disabled(): void
    {
        $this->game->update(['release_clauses_enabled' => false]);
        $this->userPlayerWithClause();

        $offers = $this->transferService->generateAIReleaseClauseTriggers(
            $this->game,
            $this->buyerPool(squadValueCents: 50_000_000_000),
        );

        $this->assertCount(0, $offers);
    }

    public function test_skips_retiring_and_clauseless_players(): void
    {
        $this->userPlayerWithClause(['retiring_at_season' => true]);
        $this->userPlayerWithClause(['release_clause' => null]);

        $offers = $this->transferService->generateAIReleaseClauseTriggers(
            $this->game,
            $this->buyerPool(squadValueCents: 50_000_000_000),
        );

        $this->assertCount(0, $offers);
    }

    public function test_does_not_re_trigger_a_player_already_being_clause_bought(): void
    {
        $this->userPlayerWithClause();
        $pool = $this->buyerPool(squadValueCents: 50_000_000_000);

        $first = $this->transferService->generateAIReleaseClauseTriggers($this->game, $pool);
        $second = $this->transferService->generateAIReleaseClauseTriggers($this->game, $pool);

        $this->assertCount(1, $first, 'First pass creates the agreed offer.');
        $this->assertCount(0, $second, 'The agreed offer makes the player ineligible on the next pass.');
    }

    public function test_notify_clause_loss_is_critical_and_deduped(): void
    {
        $player = $this->userPlayerWithClause();
        $offer = $this->clauseOffer($player);

        $notification = $this->notificationService->notifyPlayerLeftViaReleaseClause($this->game, $offer);

        $this->assertNotNull($notification);
        $this->assertSame(GameNotification::TYPE_PLAYER_LEFT_VIA_RELEASE_CLAUSE, $notification->type);
        $this->assertSame(GameNotification::PRIORITY_CRITICAL, $notification->priority);
        $this->assertSame($player->id, $notification->metadata['player_id']);
        $this->assertSame($this->buyer->id, $notification->metadata['buying_team_id']);
        $this->assertSame(self::CLAUSE, $notification->metadata['clause_amount']);

        // Same player within a day is deduped.
        $this->assertNull($this->notificationService->notifyPlayerLeftViaReleaseClause($this->game, $offer));
        $this->assertSame(1, GameNotification::where('game_id', $this->game->id)
            ->where('type', GameNotification::TYPE_PLAYER_LEFT_VIA_RELEASE_CLAUSE)->count());
    }

    public function test_completion_notification_is_suppressed_for_clause_offers(): void
    {
        $player = $this->userPlayerWithClause();
        $offer = $this->clauseOffer($player);

        $result = $this->notificationService->notifyTransferComplete($this->game, $offer);

        $this->assertNull($result, 'The generic completion notice is skipped for a triggered clause.');
        $this->assertDatabaseMissing('game_notifications', [
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_TRANSFER_COMPLETE,
        ]);
    }

    public function test_agreed_clause_offer_completes_through_the_outgoing_pipeline(): void
    {
        $player = $this->userPlayerWithClause();
        $offer = $this->clauseOffer($player);
        $offer->update(['status' => TransferOffer::STATUS_AGREED]);

        $completed = $this->transferService->completeAgreedTransfers($this->game);

        $this->assertCount(1, $completed);
        $this->assertSame(TransferOffer::STATUS_COMPLETED, $offer->fresh()->status);
        $this->assertSame($this->buyer->id, $player->fresh()->team_id, 'The player moves to the buying club.');
        $this->assertSame(
            self::BUDGET + self::CLAUSE,
            (int) $this->game->currentInvestment->fresh()->transfer_budget,
            'The clause fee is credited to the user budget.',
        );
    }

    private function userPlayerWithClause(array $overrides = []): GamePlayer
    {
        return GamePlayer::factory()->forGame($this->game)->forTeam($this->userTeam)->create(array_merge([
            'market_value_cents' => self::MV,
            'release_clause' => self::CLAUSE,
            'tier' => self::PLAYER_TIER,
        ], $overrides));
    }

    private function clauseOffer(GamePlayer $player): TransferOffer
    {
        return TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->buyer->id,
            'selling_team_id' => $this->userTeam->id,
            'offer_type' => TransferOffer::TYPE_UNSOLICITED,
            'direction' => TransferOffer::DIRECTION_OUTGOING,
            'transfer_fee' => self::CLAUSE,
            'status' => TransferOffer::STATUS_AGREED,
            'triggered_release_clause' => true,
            'negotiation_round' => 1,
            'expires_at' => '2025-08-31',
            'game_date' => '2025-08-01',
        ]);
    }

    /**
     * A hand-built buyer pool with a single eligible club, so buyer selection is
     * deterministic and affordability depends only on the squad value passed.
     *
     * @return array{leagueTeams: \Illuminate\Support\Collection, squadValues: \Illuminate\Support\Collection, reputationLevels: \Illuminate\Support\Collection}
     */
    private function buyerPool(int $squadValueCents): array
    {
        return [
            'leagueTeams' => collect([$this->buyer->id => $this->buyer]),
            'squadValues' => collect([$this->buyer->id => $squadValueCents]),
            // ELITE → min player tier 4; our tier-5 player clears the gate.
            'reputationLevels' => collect([$this->buyer->id => ClubProfile::REPUTATION_ELITE]),
        ];
    }
}
