<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Transfer\Services\DispositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the reserve-team (filial) loan-rejection relaxation: feeder squads
 * exist to develop players, so their top performers should not be treated like
 * irreplaceable first-team starters when a higher-reputation senior club asks
 * to take them on loan.
 */
class LoanRequestReserveTeamTest extends TestCase
{
    use RefreshDatabase;

    private DispositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DispositionService::class);
        // Disable the reputation-modifier gate stochasticity by seeding rand
        // through a fixed value path: the upward-gap cases all return modifier
        // 1.0, so the rand() call inside evaluateLoanRequest is never reached.
    }

    private function setReputation(Game $game, Team $team, string $level): void
    {
        TeamReputation::updateOrCreate(
            ['game_id' => $game->id, 'team_id' => $team->id],
            [
                'reputation_level' => $level,
                'base_reputation_level' => $level,
                'reputation_points' => TeamReputation::pointsForTier($level),
            ],
        );
    }

    /**
     * Build a small squad where $star ends up as the top-ranked player by
     * overall_score (importance ≈ 1.0). Ten other players sit below them, so
     * the star's rank-derived importance is well above the 0.70 threshold.
     */
    private function buildSquadWithStar(Game $game, Team $team, int $starOverall = 80): GamePlayer
    {
        $star = GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'overall_score' => $starOverall,
        ]);

        for ($i = 0; $i < 10; $i++) {
            GamePlayer::factory()->forGame($game)->forTeam($team)->create([
                'overall_score' => 50,
            ]);
        }

        return $star;
    }

    public function test_reserve_team_two_tier_jump_bypasses_key_player_gate(): void
    {
        $game = Game::factory()->create();

        $userTeam = Team::factory()->create();
        $this->setReputation($game, $userTeam, ClubProfile::REPUTATION_ELITE);
        $game->update(['team_id' => $userTeam->id]);

        $parentTeam = Team::factory()->create();
        $reserveTeam = Team::factory()->create(['parent_team_id' => $parentTeam->id]);
        $this->setReputation($game, $reserveTeam, ClubProfile::REPUTATION_LOCAL);

        $star = $this->buildSquadWithStar($game, $reserveTeam);

        // Star is the top player in a small filial squad (importance ≈ 1.0).
        // Without the relaxation this would always reject as "key player";
        // with a 2-tier upward gap it must always accept.
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $result = $this->service->evaluateLoanRequest($star->fresh(), $game->fresh());
            $this->assertSame('accepted', $result['result']);
        }
    }

    public function test_reserve_team_one_tier_jump_relaxes_but_does_not_bypass(): void
    {
        $game = Game::factory()->create();

        $userTeam = Team::factory()->create();
        $this->setReputation($game, $userTeam, ClubProfile::REPUTATION_MODEST);
        $game->update(['team_id' => $userTeam->id]);

        $parentTeam = Team::factory()->create();
        $reserveTeam = Team::factory()->create(['parent_team_id' => $parentTeam->id]);
        $this->setReputation($game, $reserveTeam, ClubProfile::REPUTATION_LOCAL);

        // Star is the top player → importance ≈ 1.0 → above the relaxed 0.90
        // threshold, so this should still reject as "key player".
        $star = $this->buildSquadWithStar($game, $reserveTeam);
        $result = $this->service->evaluateLoanRequest($star->fresh(), $game->fresh());
        $this->assertSame('rejected', $result['result']);

        // A mid-pack player (importance ≈ 0.5, below 0.90 but above 0.60)
        // hits the relaxed shared-decision band — it can resolve either way,
        // but it must not be a deterministic "key player" rejection.
        $game2 = Game::factory()->create();
        $userTeam2 = Team::factory()->create();
        $this->setReputation($game2, $userTeam2, ClubProfile::REPUTATION_MODEST);
        $game2->update(['team_id' => $userTeam2->id]);
        $reserveTeam2 = Team::factory()->create(['parent_team_id' => $parentTeam->id]);
        $this->setReputation($game2, $reserveTeam2, ClubProfile::REPUTATION_LOCAL);

        // Five players, all overall 60 except a high one above and below the
        // target: target sits in the middle (importance ≈ 0.5).
        $high = GamePlayer::factory()->forGame($game2)->forTeam($reserveTeam2)->create(['overall_score' => 80]);
        $target = GamePlayer::factory()->forGame($game2)->forTeam($reserveTeam2)->create(['overall_score' => 65]);
        GamePlayer::factory()->forGame($game2)->forTeam($reserveTeam2)->create(['overall_score' => 60]);
        GamePlayer::factory()->forGame($game2)->forTeam($reserveTeam2)->create(['overall_score' => 55]);
        GamePlayer::factory()->forGame($game2)->forTeam($reserveTeam2)->create(['overall_score' => 50]);

        $acceptedCount = 0;
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $r = $this->service->evaluateLoanRequest($target->fresh(), $game2->fresh());
            if ($r['result'] === 'accepted') {
                $acceptedCount++;
            }
        }

        // A 50/50 roll over 40 trials should land at least a few accepts.
        $this->assertGreaterThan(0, $acceptedCount);
    }

    public function test_non_reserve_team_top_player_still_rejected_as_key_player(): void
    {
        $game = Game::factory()->create();

        $userTeam = Team::factory()->create();
        $this->setReputation($game, $userTeam, ClubProfile::REPUTATION_ELITE);
        $game->update(['team_id' => $userTeam->id]);

        // A standalone senior team — no parent_team_id, so the reserve-team
        // relaxation must not apply even with a large upward reputation gap.
        $seniorTeam = Team::factory()->create(['parent_team_id' => null]);
        $this->setReputation($game, $seniorTeam, ClubProfile::REPUTATION_LOCAL);

        $star = $this->buildSquadWithStar($game, $seniorTeam);

        $result = $this->service->evaluateLoanRequest($star->fresh(), $game->fresh());
        $this->assertSame('rejected', $result['result']);
    }

    public function test_reserve_team_lateral_or_downward_request_keeps_default_gate(): void
    {
        // A modest-tier reserve team (rare in practice but exercises the
        // boundary) being asked by a local-tier user club: no upward gap, so
        // the default 0.70 threshold applies and the star is rejected.
        $game = Game::factory()->create();

        $userTeam = Team::factory()->create();
        $this->setReputation($game, $userTeam, ClubProfile::REPUTATION_LOCAL);
        $game->update(['team_id' => $userTeam->id]);

        $parentTeam = Team::factory()->create();
        $reserveTeam = Team::factory()->create(['parent_team_id' => $parentTeam->id]);
        $this->setReputation($game, $reserveTeam, ClubProfile::REPUTATION_LOCAL);

        $star = $this->buildSquadWithStar($game, $reserveTeam);

        $result = $this->service->evaluateLoanRequest($star->fresh(), $game->fresh());
        $this->assertSame('rejected', $result['result']);
    }

    public function test_sync_evaluation_also_relaxes_for_reserve_team(): void
    {
        $game = Game::factory()->create();

        $userTeam = Team::factory()->create();
        $this->setReputation($game, $userTeam, ClubProfile::REPUTATION_ELITE);
        $game->update(['team_id' => $userTeam->id]);

        $parentTeam = Team::factory()->create();
        $reserveTeam = Team::factory()->create(['parent_team_id' => $parentTeam->id]);
        $this->setReputation($game, $reserveTeam, ClubProfile::REPUTATION_LOCAL);

        $star = $this->buildSquadWithStar($game, $reserveTeam);

        $result = $this->service->evaluateLoanRequestSync($star->fresh(), $game->fresh());
        // Importance gate must not fire; whatever the player-willingness path
        // decides, the rejection_reason must not be "key_player".
        $this->assertNotSame('key_player', $result['rejection_reason']);
    }
}
