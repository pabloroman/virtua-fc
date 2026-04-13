<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the "needs penalties" decision after extra time.
 *
 * Bug: buildExtraTimeData (page-refresh path) unconditionally set
 * needsPenalties=true whenever ET was played and penalties hadn't been
 * resolved — even if the ET score was not a draw. Users who refreshed
 * after a decisive ET result were sent to the penalty picker incorrectly.
 */
class ExtraTimePenaltyDecisionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $cupCompetition;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->playerTeam = Team::factory()->create(['name' => 'Spain']);
        $this->opponentTeam = Team::factory()->create(['name' => 'Austria']);

        $this->cupCompetition = Competition::factory()->knockoutCup()->create([
            'id' => 'CUP1',
            'name' => 'Test Cup',
        ]);

        $this->playerTeam->competitions()->attach($this->cupCompetition->id, ['season' => '2024']);
        $this->opponentTeam->competitions()->attach($this->cupCompetition->id, ['season' => '2024']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->cupCompetition->id,
            'season' => '2024',
            'current_date' => '2024-08-15',
        ]);
    }

    public function test_penalties_not_needed_when_et_has_a_winner(): void
    {
        $service = app(ExtraTimeAndPenaltyService::class);

        $match = $this->createKnockoutMatch(homeScore: 1, awayScore: 1);

        // ET result: 1-0 for home → total 2-1, not a draw
        $result = $service->checkNeedsPenalties($match, 1, 0);

        $this->assertFalse($result, 'Penalties should not be needed when ET produces a winner');
    }

    public function test_penalties_needed_when_et_is_a_draw(): void
    {
        $service = app(ExtraTimeAndPenaltyService::class);

        $match = $this->createKnockoutMatch(homeScore: 1, awayScore: 1);

        // ET result: 0-0 → total 1-1, still a draw
        $result = $service->checkNeedsPenalties($match, 0, 0);

        $this->assertTrue($result, 'Penalties should be needed when ET is a draw');
    }

    /**
     * The exact bug reported by the user: ET ends 2-1 but page refresh
     * incorrectly triggers the penalty picker.
     */
    public function test_page_refresh_after_et_winner_does_not_trigger_penalties(): void
    {
        $this->actingAs($this->user);

        $match = $this->createKnockoutMatch(homeScore: 1, awayScore: 1);

        // Simulate ET already played with a decisive result
        $match->update([
            'is_extra_time' => true,
            'home_score_et' => 1,
            'away_score_et' => 0,
            // home_score_penalties is null — penalties not played
        ]);

        $this->game->update(['pending_finalization_match_id' => $match->id]);

        // Load the live match page (simulates page refresh)
        $response = $this->get(route('show-live-match', [
            'gameId' => $this->game->id,
            'matchId' => $match->id,
        ]));

        $response->assertOk();

        // The preloaded ET data should NOT trigger penalties
        $response->assertViewHas('extraTimeData', function ($data) {
            return $data['needsPenalties'] === false
                && $data['homeScoreET'] === 1
                && $data['awayScoreET'] === 0;
        });
    }

    public function test_page_refresh_after_et_draw_triggers_penalties(): void
    {
        $this->actingAs($this->user);

        $match = $this->createKnockoutMatch(homeScore: 1, awayScore: 1);

        // Simulate ET already played with a draw
        $match->update([
            'is_extra_time' => true,
            'home_score_et' => 0,
            'away_score_et' => 0,
        ]);

        $this->game->update(['pending_finalization_match_id' => $match->id]);

        $response = $this->get(route('show-live-match', [
            'gameId' => $this->game->id,
            'matchId' => $match->id,
        ]));

        $response->assertOk();

        $response->assertViewHas('extraTimeData', function ($data) {
            return $data['needsPenalties'] === true;
        });
    }

    public function test_two_legged_aggregate_draw_triggers_penalties(): void
    {
        $service = app(ExtraTimeAndPenaltyService::class);

        // First leg: home 1-0 away
        $firstLeg = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-10'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $cupTie = CupTie::factory()
            ->forGame($this->game)
            ->between($this->playerTeam, $this->opponentTeam)
            ->create([
                'competition_id' => $this->cupCompetition->id,
                'first_leg_match_id' => $firstLeg->id,
            ]);

        // Second leg (reversed home/away): 0-1 → aggregate 1-1
        $secondLeg = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->opponentTeam->id,
            'away_team_id' => $this->playerTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-17'),
            'played' => true,
            'home_score' => 0,
            'away_score' => 1,
            'cup_tie_id' => $cupTie->id,
        ]);

        $cupTie->update(['second_leg_match_id' => $secondLeg->id]);

        // ET 0-0 → aggregate still 1-1 → penalties needed
        $result = $service->checkNeedsPenalties($secondLeg->fresh(), 0, 0);

        $this->assertTrue($result, 'Penalties should be needed when two-legged aggregate is a draw after ET');
    }

    public function test_two_legged_aggregate_winner_after_et_skips_penalties(): void
    {
        $service = app(ExtraTimeAndPenaltyService::class);

        $firstLeg = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-10'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $cupTie = CupTie::factory()
            ->forGame($this->game)
            ->between($this->playerTeam, $this->opponentTeam)
            ->create([
                'competition_id' => $this->cupCompetition->id,
                'first_leg_match_id' => $firstLeg->id,
            ]);

        $secondLeg = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->opponentTeam->id,
            'away_team_id' => $this->playerTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-17'),
            'played' => true,
            'home_score' => 0,
            'away_score' => 1,
            'cup_tie_id' => $cupTie->id,
        ]);

        $cupTie->update(['second_leg_match_id' => $secondLeg->id]);

        // ET 1-0 for second leg home → aggregate: home 1+0=1, away 0+1+1=2 → away wins
        $result = $service->checkNeedsPenalties($secondLeg->fresh(), 1, 0);

        $this->assertFalse($result, 'Penalties should not be needed when aggregate has a winner after ET');
    }

    private function createKnockoutMatch(int $homeScore = 0, int $awayScore = 0): GameMatch
    {
        $this->createSquad($this->playerTeam);
        $this->createSquad($this->opponentTeam);

        $homeLineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $awayLineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->opponentTeam->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $cupTie = CupTie::factory()
            ->forGame($this->game)
            ->between($this->playerTeam, $this->opponentTeam)
            ->create(['competition_id' => $this->cupCompetition->id]);

        return GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->cupCompetition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
            'played' => true,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'home_lineup' => $homeLineup,
            'away_lineup' => $awayLineup,
            'cup_tie_id' => $cupTie->id,
        ]);
    }

    private function createSquad(Team $team): void
    {
        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->goalkeeper()
            ->create();

        foreach (['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back'] as $pos) {
            GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['position' => $pos]);
        }

        GamePlayer::factory()->forGame($this->game)->forTeam($team)->count(4)->create(['position' => 'Central Midfield']);

        foreach (['Centre-Forward', 'Centre-Forward'] as $pos) {
            GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['position' => $pos]);
        }
    }
}
