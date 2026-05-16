<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Models\User;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the goalkeeper-drain exploit: a user could sign
 * every one of a rival's goalkeepers because TransferService never asked
 * whether the AI seller could legitimately part with the player. A
 * seller-side guard now refuses sales that would drop the AI more than one
 * player short of the normal floors — so a rival can be raided down to a
 * lone keeper, but the user can never strip a position bare, even by
 * stacking parallel negotiations on multiple of the seller's players.
 */
class SellerSquadMinimumGuardTest extends TestCase
{
    use RefreshDatabase;

    private TransferService $transferService;
    private ScoutingService $scoutingService;
    private User $user;
    private Team $userTeam;
    private Team $aiTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = app(TransferService::class);
        $this->scoutingService = app(ScoutingService::class);

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->aiTeam = Team::factory()->create(['name' => 'AI Team']);

        $competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => $competition->id,
            'current_date' => '2025-08-01',
        ]);

        GameInvestment::create([
            'game_id' => $this->game->id,
            'season' => $this->game->season,
            'transfer_budget' => 500_000_000_00, // €500M — never the bottleneck
            'scouting_tier' => 1,
        ]);
    }

    public function test_seller_refuses_bid_that_would_empty_a_position(): void
    {
        // AI down to its last keeper — selling it would leave them with no
        // goalkeeper at all, which is the absolute floor the guard protects.
        $this->fillSquad($this->aiTeam, goalkeepers: 1);
        $targetGk = $this->aiTeamGoalkeepers()->first();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AI Team');

        $this->transferService->negotiateTransferFeeSync(
            $this->game, $targetGk, 500_000_000_00, $this->scoutingService,
        );
    }

    public function test_seller_accepts_bid_that_dips_one_player_short(): void
    {
        // AI sitting at the global floor of 2 keepers: the leniency lets a
        // third-party bid take them down to 1.
        $this->fillSquad($this->aiTeam, goalkeepers: 2);
        $targetGk = $this->aiTeamGoalkeepers()->first();

        $result = $this->transferService->negotiateTransferFeeSync(
            $this->game, $targetGk, 500_000_000_00, $this->scoutingService,
        );

        $this->assertContains($result['result'], ['accepted', 'countered'],
            'A bid that leaves the seller one short of the global floor must still reach the AI.');
    }

    public function test_parallel_bids_cannot_drain_a_position_below_minimum(): void
    {
        // AI has 2 GKs. The first commitment is already in flight (FEE_AGREED),
        // so the second bid must be refused — otherwise the user could strip
        // the position bare via two parallel negotiations.
        $this->fillSquad($this->aiTeam, goalkeepers: 2);
        $goalkeepers = $this->aiTeamGoalkeepers();
        [$gk1, $gk2] = [$goalkeepers->get(0), $goalkeepers->get(1)];

        // Stack the first commitment at FEE_AGREED — that's the binding
        // state the user reaches after the AI accepts the fee.
        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $gk1->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->aiTeam->id,
            'offer_type' => TransferOffer::TYPE_USER_BID,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 500_000_000_00,
            'status' => TransferOffer::STATUS_FEE_AGREED,
            'expires_at' => '2025-08-31',
            'game_date' => '2025-08-01',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->transferService->negotiateTransferFeeSync(
            $this->game, $gk2, 500_000_000_00, $this->scoutingService,
        );
    }

    public function test_seller_still_allows_bids_on_unrestricted_positions(): void
    {
        // AI is at its last keeper, but the bid is on a forward whose
        // position group has plenty of depth. The guard must scope to the
        // affected position group.
        $this->fillSquad($this->aiTeam, goalkeepers: 1);
        $targetForward = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->where('position', 'Centre-Forward')
            ->first();

        $result = $this->transferService->negotiateTransferFeeSync(
            $this->game, $targetForward, 500_000_000_00, $this->scoutingService,
        );

        $this->assertContains($result['result'], ['accepted', 'countered'],
            'A bid that doesn\'t threaten any position floor must reach the AI for evaluation.');
    }

    private function aiTeamGoalkeepers()
    {
        return GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->where('position', 'Goalkeeper')
            ->get();
    }

    /**
     * Build a roster that sits above the seller-side tolerant floors so a
     * single sale never trips the total-squad or non-goalkeeper position
     * guards: total stays >= MIN_SQUAD_SIZE - 1 (19) post-sale, and each
     * non-GK group stays one above its tolerant floor. That keeps each
     * test's assertion focused on the goalkeeper floor, set explicitly via
     * the $goalkeepers argument.
     */
    private function fillSquad(Team $team, int $goalkeepers): void
    {
        $positions = [
            ['Goalkeeper', $goalkeepers],
            ['Centre-Back', 7],      // Defender tolerant floor 5
            ['Central Midfield', 7], // Midfielder tolerant floor 5
            ['Centre-Forward', 5],   // Forward tolerant floor 3
        ];

        foreach ($positions as [$position, $count]) {
            for ($i = 0; $i < $count; $i++) {
                GamePlayer::factory()->create([
                    'game_id' => $this->game->id,
                    'team_id' => $team->id,
                    'position' => $position,
                    'market_value_cents' => 50_000_000_00,
                ]);
            }
        }
    }
}
