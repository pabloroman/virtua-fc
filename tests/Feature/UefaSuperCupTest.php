<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\UefaSuperCupQualificationProcessor;
use App\Modules\Season\Services\SeasonInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers two pieces of UEFA Super Cup behavior:
 *
 *  1. UefaSuperCupQualificationProcessor rewrites CompetitionEntry rows
 *     from META_UCL_WINNER / META_UEL_WINNER in subsequent seasons, and
 *     clears stale entries when metadata is incomplete.
 *
 *  2. SeasonInitializationService::conductCupDraws skips the draw when
 *     the user's team isn't a participant — mirroring the Swiss-format
 *     behavior for UCL/UEL/UECL.
 */
class UefaSuperCupTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $userTeam;

    protected function setUp(): void
    {
        parent::setUp();

        // The Game's competition_id FK requires ESP1 to exist.
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);

        // UEFA Super Cup — single-leg continental knockout.
        Competition::factory()->create([
            'id' => 'UEFASUP',
            'name' => 'UEFA Super Cup',
            'country' => 'EU',
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
            'handler_type' => 'knockout_cup',
            'season' => '2025',
        ]);

        $user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);
    }

    // =========================================
    // UefaSuperCupQualificationProcessor
    // =========================================

    public function test_processor_writes_two_entries_when_both_winners_in_metadata(): void
    {
        $uclWinner = Team::factory()->create(['name' => 'UCL Winner', 'country' => 'FR']);
        $uelWinner = Team::factory()->create(['name' => 'UEL Winner', 'country' => 'EN']);

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
            isInitialSeason: false,
        );
        $data->setMetadata(SeasonTransitionData::META_UCL_WINNER, $uclWinner->id);
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $uelWinner->id);

        app(UefaSuperCupQualificationProcessor::class)->process($this->game, $data);

        $entries = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UEFASUP')
            ->pluck('team_id')
            ->toArray();

        $this->assertCount(2, $entries);
        $this->assertContains($uclWinner->id, $entries);
        $this->assertContains($uelWinner->id, $entries);
    }

    public function test_processor_wipes_entries_when_metadata_incomplete(): void
    {
        // Leftover finalists from the previous season — stale data the
        // processor must not carry forward into the new season.
        $stale1 = Team::factory()->create();
        $stale2 = Team::factory()->create();
        foreach ([$stale1, $stale2] as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UEFASUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        // Simulate a partial rollover: only UEL winner captured, UCL missing.
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
            isInitialSeason: false,
        );
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, Team::factory()->create()->id);

        app(UefaSuperCupQualificationProcessor::class)->process($this->game, $data);

        $this->assertEquals(
            0,
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEFASUP')
                ->count(),
            'Stale entries should be wiped when metadata is incomplete',
        );
    }

    // =========================================
    // conductCupDraws user-participation gate
    // =========================================

    public function test_conduct_cup_draws_skips_uefasup_when_user_not_participant(): void
    {
        // UEFASUP entries are the two 2024/25 winners, neither is the user.
        $psg = Team::factory()->create(['name' => 'Paris Saint-Germain']);
        $spurs = Team::factory()->create(['name' => 'Tottenham Hotspur']);

        foreach ([$psg, $spurs] as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UEFASUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        app(SeasonInitializationService::class)->conductCupDraws($this->game->id, 'ES');

        $this->assertEquals(
            0,
            GameMatch::where('game_id', $this->game->id)
                ->where('competition_id', 'UEFASUP')
                ->count(),
            'No UEFASUP match should be drawn when the user is not a finalist',
        );
    }

    public function test_conduct_cup_draws_creates_uefasup_match_when_user_is_participant(): void
    {
        $otherFinalist = Team::factory()->create(['name' => 'Other Finalist']);

        foreach ([$this->userTeam, $otherFinalist] as $team) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UEFASUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        app(SeasonInitializationService::class)->conductCupDraws($this->game->id, 'ES');

        $match = GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'UEFASUP')
            ->first();

        $this->assertNotNull($match, 'A UEFASUP match should be drawn when the user is a finalist');
        $this->assertEquals('2025-08-13', $match->scheduled_date->toDateString());
        $this->assertEquals('cup.final', $match->round_name);
        $this->assertContains(
            $this->userTeam->id,
            [$match->home_team_id, $match->away_team_id],
            'The user team must be one of the two participants',
        );
    }
}
