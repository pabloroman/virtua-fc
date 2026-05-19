<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\ManagerJobHistory;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Manager\Processors\ApplyPendingTeamSwitchProcessor;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\TeamReputationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ApplyPendingTeamSwitchProcessorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: the offer's competition_id was stamped before the closing
     * pipeline ran. If PromotionRelegationProcessor moved the destination
     * team between leagues during closing, the offer's competition_id is
     * stale by the time this processor runs. The processor must read
     * competition_entries (post-closing state) instead of trusting the offer.
     */
    public function test_uses_competition_entries_when_offer_competition_is_stale(): void
    {
        [$processor, $game, $newTeam, $offer, $espTwo, $espThreeB] = $this->buildScenario(
            offerCompetitionId: 'ESP2',
            actualEntryCompetitionId: 'ESP3B',
        );

        $data = new SeasonTransitionData(
            oldSeason: '2027',
            newSeason: '2028',
            competitionId: $game->competition_id,
        );

        $processor->process($game->refresh(), $data);

        $this->assertSame('ESP3B', $game->refresh()->competition_id,
            'Game.competition_id should resolve to the team\'s actual league, not the stale offer value.');
        $this->assertSame($newTeam->id, $game->team_id);
        $this->assertSame('ESP3B', $data->competitionId,
            'Setup-pipeline DTO must be updated so LeagueFixtureProcessor regenerates against the real league.');
        $this->assertNull($game->pending_team_switch);

        $tenure = ManagerJobHistory::where('game_id', $game->id)
            ->whereNull('season_end')
            ->first();
        $this->assertNotNull($tenure);
        $this->assertSame($newTeam->id, $tenure->team_id);
        $this->assertSame('ESP3B', $tenure->competition_id);
    }

    /**
     * When the offer's competition_id still matches the destination team's
     * actual entry (no mid-transition promotion/relegation), the processor
     * should land on the same value either way.
     */
    public function test_preserves_offer_competition_when_entries_agree(): void
    {
        [$processor, $game, $newTeam] = $this->buildScenario(
            offerCompetitionId: 'ESP2',
            actualEntryCompetitionId: 'ESP2',
        );

        $data = new SeasonTransitionData(
            oldSeason: '2027',
            newSeason: '2028',
            competitionId: $game->competition_id,
        );

        $processor->process($game->refresh(), $data);

        $this->assertSame('ESP2', $game->refresh()->competition_id);
        $this->assertSame($newTeam->id, $game->team_id);
    }

    /**
     * @return array{0: ApplyPendingTeamSwitchProcessor, 1: Game, 2: Team, 3: ManagerJobOffer, 4: Competition, 5: Competition}
     */
    private function buildScenario(string $offerCompetitionId, string $actualEntryCompetitionId): array
    {
        $espTwo = Competition::factory()->create([
            'id' => 'ESP2',
            'name' => 'LaLiga2',
            'country' => 'ES',
            'tier' => 2,
            'role' => Competition::ROLE_LEAGUE,
            'handler_type' => 'league_with_playoff',
            'scope' => Competition::SCOPE_DOMESTIC,
        ]);

        $espThreeB = Competition::factory()->create([
            'id' => 'ESP3B',
            'name' => 'Primera Federación - Grupo II',
            'country' => 'ES',
            'tier' => 3,
            'role' => Competition::ROLE_LEAGUE,
            'handler_type' => 'league_with_playoff',
            'scope' => Competition::SCOPE_DOMESTIC,
        ]);

        $user = User::factory()->create();
        $oldTeam = Team::factory()->create(['country' => 'ES']);
        $newTeam = Team::factory()->create(['country' => 'ES']);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'game_mode' => Game::MODE_CAREER_PRO,
            'country' => 'ES',
            'team_id' => $oldTeam->id,
            'competition_id' => 'ESP3A',
            'season' => '2028',
        ]);

        CompetitionEntry::create([
            'game_id' => $game->id,
            'competition_id' => $actualEntryCompetitionId,
            'team_id' => $newTeam->id,
            'entry_round' => 1,
        ]);

        $offer = ManagerJobOffer::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'team_id' => $newTeam->id,
            'competition_id' => $offerCompetitionId,
            'season' => '2027',
            'offer_type' => ManagerJobOffer::TYPE_END_OF_SEASON,
            'status' => ManagerJobOffer::STATUS_ACCEPTED,
            'source_reputation_level' => 'local',
            'target_reputation_level' => 'modest',
        ]);

        $game->update(['pending_team_switch' => $offer->id]);

        $processor = new ApplyPendingTeamSwitchProcessor(
            Mockery::mock(JobOfferService::class),
            Mockery::mock(TeamReputationSeeder::class)->shouldIgnoreMissing(),
        );

        return [$processor, $game, $newTeam, $offer, $espTwo, $espThreeB];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
