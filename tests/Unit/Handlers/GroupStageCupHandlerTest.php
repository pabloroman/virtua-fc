<?php

namespace Tests\Unit\Handlers;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\WorldCupKnockoutGenerator;
use App\Modules\Match\Handlers\GroupStageCupHandler;
use App\Modules\Match\Services\CupTieResolver;
use App\Modules\Squad\Services\EligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GroupStageCupHandlerTest extends TestCase
{
    use RefreshDatabase;

    private GroupStageCupHandler $handler;
    private Game $game;
    private Competition $wcCompetition;
    private Team $userTeam;
    private Team $teamB;
    private Team $teamC;
    private Team $teamD;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();

        $this->userTeam = Team::factory()->create(['name' => 'Brasil']);
        $this->teamB = Team::factory()->create(['name' => 'Argentina']);
        $this->teamC = Team::factory()->create(['name' => 'Netherlands']);
        $this->teamD = Team::factory()->create(['name' => 'Spain']);

        $this->wcCompetition = Competition::factory()->groupStageCup()->create([
            'id' => 'WC2026',
            'name' => 'World Cup 2026',
            'season' => '2025',
        ]);

        // Need a league competition for the game's primary competition
        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'WC2026',
            'game_mode' => Game::MODE_TOURNAMENT,
            'season' => '2025',
            'current_date' => '2026-07-14',
        ]);

        $this->handler = new GroupStageCupHandler(
            Mockery::mock(CupTieResolver::class),
            new EligibilityService(),
            app(WorldCupKnockoutGenerator::class),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Simulate that all group-stage matches have been played.
     * The handler checks isGroupStageComplete before generating knockout rounds.
     */
    private function markGroupStageComplete(): void
    {
        // Create a played group-stage match (no cup_tie_id) so isGroupStageComplete returns true
        // and hasMatches check passes. All group matches must be played (no unplayed ones).
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->wcCompetition->id,
            'cup_tie_id' => null,
            'played' => true,
            'scheduled_date' => '2026-06-20',
            'home_score' => 1,
            'away_score' => 0,
        ]);
    }

    /**
     * Create a completed cup tie with its played first-leg match.
     */
    private function createCompletedTie(
        Team $homeTeam,
        Team $awayTeam,
        Team $winner,
        int $roundNumber,
        string $date,
        int $homeScore = 2,
        int $awayScore = 1,
        ?int $bracketPosition = null,
    ): CupTie {
        $tie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->wcCompetition->id,
            'round_number' => $roundNumber,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'winner_id' => $winner->id,
            'completed' => true,
            'resolution' => ['type' => 'normal'],
            'bracket_position' => $bracketPosition,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->wcCompetition->id,
            'cup_tie_id' => $tie->id,
            'round_number' => $roundNumber,
            'home_team_id' => $homeTeam->id,
            'away_team_id' => $awayTeam->id,
            'scheduled_date' => $date,
            'played' => true,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);

        $tie->update(['first_leg_match_id' => $match->id]);

        return $tie;
    }

    public function test_generates_third_place_and_final_after_both_semifinals_complete(): void
    {
        $this->markGroupStageComplete();

        // Create two completed semifinal ties
        // SF1: Brasil (user) beat Argentina
        $this->createCompletedTie(
            homeTeam: $this->userTeam,
            awayTeam: $this->teamB,
            winner: $this->userTeam,
            roundNumber: WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            date: '2026-07-14',
            homeScore: 3,
            awayScore: 0,
            bracketPosition: 101,
        );

        // SF2: Netherlands beat Spain
        $this->createCompletedTie(
            homeTeam: $this->teamC,
            awayTeam: $this->teamD,
            winner: $this->teamC,
            roundNumber: WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            date: '2026-07-14',
            homeScore: 2,
            awayScore: 1,
            bracketPosition: 102,
        );

        // Verify no third-place or final matches exist yet
        $this->assertFalse(
            CupTie::where('game_id', $this->game->id)
                ->where('round_number', WorldCupKnockoutGenerator::ROUND_THIRD_PLACE)
                ->exists()
        );
        $this->assertFalse(
            CupTie::where('game_id', $this->game->id)
                ->where('round_number', WorldCupKnockoutGenerator::ROUND_FINAL)
                ->exists()
        );

        // Trigger beforeMatches which should generate the next round
        $this->handler->beforeMatches($this->game, '2026-07-14');

        // Verify third-place match was generated (SF losers: Argentina vs Spain)
        $thirdPlaceTie = CupTie::where('game_id', $this->game->id)
            ->where('competition_id', $this->wcCompetition->id)
            ->where('round_number', WorldCupKnockoutGenerator::ROUND_THIRD_PLACE)
            ->first();

        $this->assertNotNull($thirdPlaceTie, 'Third-place tie should be generated after both SFs complete');
        $losers = [$thirdPlaceTie->home_team_id, $thirdPlaceTie->away_team_id];
        $this->assertContains($this->teamB->id, $losers, 'Argentina should be in 3rd-place match');
        $this->assertContains($this->teamD->id, $losers, 'Spain should be in 3rd-place match');

        // Verify final was generated (SF winners: Brasil vs Netherlands)
        $finalTie = CupTie::where('game_id', $this->game->id)
            ->where('competition_id', $this->wcCompetition->id)
            ->where('round_number', WorldCupKnockoutGenerator::ROUND_FINAL)
            ->first();

        $this->assertNotNull($finalTie, 'Final tie should be generated after both SFs complete');
        $finalists = [$finalTie->home_team_id, $finalTie->away_team_id];
        $this->assertContains($this->userTeam->id, $finalists, 'Brasil should be in the final');
        $this->assertContains($this->teamC->id, $finalists, 'Netherlands should be in the final');

        // Verify the user's team can find their next match (the final)
        $userNextMatch = GameMatch::where('game_id', $this->game->id)
            ->where('played', false)
            ->where(fn ($q) => $q->where('home_team_id', $this->userTeam->id)
                ->orWhere('away_team_id', $this->userTeam->id))
            ->first();

        $this->assertNotNull($userNextMatch, 'User should have the final as their next match');
        $this->assertEquals($finalTie->id, $userNextMatch->cup_tie_id);
    }

    public function test_does_not_generate_final_when_only_one_semifinal_complete(): void
    {
        $this->markGroupStageComplete();

        // Only one SF completed
        $this->createCompletedTie(
            homeTeam: $this->userTeam,
            awayTeam: $this->teamB,
            winner: $this->userTeam,
            roundNumber: WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            date: '2026-07-14',
            bracketPosition: 101,
        );

        // Other SF exists but NOT completed
        $incompleteTie = CupTie::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->wcCompetition->id,
            'round_number' => WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            'home_team_id' => $this->teamC->id,
            'away_team_id' => $this->teamD->id,
            'completed' => false,
            'bracket_position' => 102,
        ]);

        // Create the unplayed match for the incomplete tie
        GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->wcCompetition->id,
            'cup_tie_id' => $incompleteTie->id,
            'round_number' => WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            'scheduled_date' => '2026-07-14',
            'played' => false,
        ]);

        $this->handler->beforeMatches($this->game, '2026-07-14');

        // Should NOT generate third-place or final
        $this->assertFalse(
            CupTie::where('game_id', $this->game->id)
                ->where('round_number', WorldCupKnockoutGenerator::ROUND_FINAL)
                ->exists(),
            'Final should not be generated when only one SF is complete'
        );
    }

    public function test_does_not_generate_duplicates_when_called_twice(): void
    {
        $this->markGroupStageComplete();

        $this->createCompletedTie(
            homeTeam: $this->userTeam,
            awayTeam: $this->teamB,
            winner: $this->userTeam,
            roundNumber: WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            date: '2026-07-14',
            bracketPosition: 101,
        );

        $this->createCompletedTie(
            homeTeam: $this->teamC,
            awayTeam: $this->teamD,
            winner: $this->teamC,
            roundNumber: WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            date: '2026-07-14',
            bracketPosition: 102,
        );

        // Call beforeMatches twice (simulates both finalize and ShowGame safety net)
        $this->handler->beforeMatches($this->game, '2026-07-14');
        $this->handler->beforeMatches($this->game, '2026-07-14');

        $finalCount = CupTie::where('game_id', $this->game->id)
            ->where('round_number', WorldCupKnockoutGenerator::ROUND_FINAL)
            ->count();

        $this->assertEquals(1, $finalCount, 'Should not create duplicate final ties');
    }

    public function test_does_not_generate_when_pending_finalization(): void
    {
        $this->markGroupStageComplete();

        $this->createCompletedTie(
            homeTeam: $this->userTeam,
            awayTeam: $this->teamB,
            winner: $this->userTeam,
            roundNumber: WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            date: '2026-07-14',
            bracketPosition: 101,
        );

        $sfTie2 = $this->createCompletedTie(
            homeTeam: $this->teamC,
            awayTeam: $this->teamD,
            winner: $this->teamC,
            roundNumber: WorldCupKnockoutGenerator::ROUND_SEMI_FINALS,
            date: '2026-07-14',
            bracketPosition: 102,
        );

        // Simulate pending finalization for a WC match
        $pendingMatch = GameMatch::where('cup_tie_id', $sfTie2->id)->first();
        $this->game->update(['pending_finalization_match_id' => $pendingMatch->id]);

        $this->handler->beforeMatches($this->game, '2026-07-14');

        $this->assertFalse(
            CupTie::where('game_id', $this->game->id)
                ->where('round_number', WorldCupKnockoutGenerator::ROUND_FINAL)
                ->exists(),
            'Should not generate when a match is pending finalization'
        );
    }
}
