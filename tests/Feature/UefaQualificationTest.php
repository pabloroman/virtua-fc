<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use App\Modules\Season\Processors\UefaQualificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UefaQualificationTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private CountryConfig $countryConfig;

    /** @var array<string, Team[]> country => teams */
    private array $teamsByCountry = [];

    /** @var Team[] extra EUR pool teams with no configured country */
    private array $eurPoolTeams = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryConfig = app(CountryConfig::class);

        // Create competitions
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'ENG1', 'country' => 'EN', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'DEU1', 'country' => 'DE', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'ITA1', 'country' => 'IT', 'tier' => 1]);
        Competition::factory()->league()->create(['id' => 'FRA1', 'country' => 'FR', 'tier' => 1]);

        // Segunda División (for relegation tests)
        Competition::factory()->league()->create(['id' => 'ESP2', 'country' => 'ES', 'tier' => 2]);

        // Copa del Rey (for cup winner tests)
        Competition::factory()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
            'country' => 'ES',
            'type' => 'cup',
            'role' => Competition::ROLE_DOMESTIC_CUP,
            'handler_type' => 'knockout_cup',
        ]);

        // EUR team pool competition
        Competition::factory()->create([
            'id' => 'EUR',
            'name' => 'European Pool',
            'country' => 'EU',
            'type' => 'league',
            'role' => Competition::ROLE_TEAM_POOL,
            'handler_type' => 'team_pool',
        ]);

        // Swiss format competitions
        Competition::factory()->create([
            'id' => 'UCL',
            'name' => 'Champions League',
            'country' => 'EU',
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
            'handler_type' => 'swiss_format',
        ]);
        Competition::factory()->create([
            'id' => 'UEL',
            'name' => 'Europa League',
            'country' => 'EU',
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
            'handler_type' => 'swiss_format',
        ]);
        Competition::factory()->create([
            'id' => 'UECL',
            'name' => 'Conference League',
            'country' => 'EU',
            'type' => 'cup',
            'role' => Competition::ROLE_EUROPEAN,
            'scope' => Competition::SCOPE_CONTINENTAL,
            'handler_type' => 'swiss_format',
        ]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team', 'country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);

        // Create teams per configured country with standings
        $this->createCountryTeamsWithStandings('ES', 'ESP1', 20);
        $this->createCountryTeamsWithStandings('EN', 'ENG1', 20);
        $this->createCountryTeamsWithStandings('DE', 'DEU1', 18);
        $this->createCountryTeamsWithStandings('IT', 'ITA1', 20);
        $this->createCountryTeamsWithStandings('FR', 'FRA1', 18);

        // Create EUR pool teams (non-configured countries) — enough to fill UCL, UEL, and UECL
        // Need ~75 filler teams (108 total slots minus 33 from configured countries)
        $eurCountries = ['PT', 'NL', 'BE', 'TR', 'GR', 'AT', 'PL', 'CZ', 'RO', 'RS', 'HR', 'NO', 'CH', 'IL', 'DK', 'SE', 'UA', 'SC'];
        for ($i = 0; $i < 80; $i++) {
            $country = $eurCountries[$i % count($eurCountries)];
            $team = Team::factory()->create(['country' => $country]);
            $this->eurPoolTeams[] = $team;

            // Register in the EUR competition pool
            CompetitionTeam::create([
                'competition_id' => 'EUR',
                'team_id' => $team->id,
                'season' => '2025',
            ]);

            // Create GamePlayer records for squad presence
            $this->createPlayersForTeam($team, 5_000_000_00 - ($i * 50_000_00));
        }

        // Seed initial CompetitionEntry records for UCL and UEL
        // (mimicking what the initial game setup would create)
        $this->seedInitialContinentalEntries();
    }

    public function test_ucl_has_36_entries_after_qualification(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();

        $processor->process($this->game, $data);

        $uclCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->count();

        $this->assertEquals(36, $uclCount, "UCL should have exactly 36 entries, got {$uclCount}");
    }

    public function test_uel_has_36_entries_after_qualification(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();

        $processor->process($this->game, $data);

        $uelCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UEL')
            ->count();

        $this->assertEquals(36, $uelCount, "UEL should have exactly 36 entries, got {$uelCount}");
    }

    public function test_uel_winner_qualifies_for_ucl(): void
    {
        // Pick a UEL team as the winner
        $uelWinner = $this->eurPoolTeams[0];

        // Ensure the winner is in UEL entries (not UCL)
        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $this->game->id,
                'competition_id' => 'UEL',
                'team_id' => $uelWinner->id,
            ],
            ['entry_round' => 1]
        );

        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $uelWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        // UEL winner should now be in UCL
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $uelWinner->id)
                ->exists(),
            'UEL winner should be in UCL entries'
        );
    }

    public function test_uel_winner_already_in_ucl_is_not_duplicated(): void
    {
        // Pick a team that's already in UCL (from standings-based qualification)
        $espTeam1 = $this->teamsByCountry['ES'][0]; // Position 1 in ESP1 standings

        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $espTeam1->id);

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        // Should still have exactly 36 entries (not 37)
        $uclCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->count();

        $this->assertEquals(36, $uclCount, "UCL should still have exactly 36 entries, got {$uclCount}");
    }

    public function test_no_team_appears_in_both_ucl_and_uel(): void
    {
        $data = $this->makeTransitionData();

        $processor = app(UefaQualificationProcessor::class);
        $processor->process($this->game, $data);

        $uclTeams = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->pluck('team_id')
            ->toArray();

        $uelTeams = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UEL')
            ->pluck('team_id')
            ->toArray();

        $overlap = array_intersect($uclTeams, $uelTeams);
        $this->assertEmpty($overlap, 'No team should appear in both UCL and UEL');
    }

    public function test_archive_processor_captures_uel_winner_from_cup_tie(): void
    {
        $winnerTeam = $this->eurPoolTeams[0];

        // Create a completed UEL final cup tie
        CupTie::create([
            'game_id' => $this->game->id,
            'competition_id' => 'UEL',
            'round_number' => 5, // SwissKnockoutGenerator::ROUND_FINAL
            'home_team_id' => $winnerTeam->id,
            'away_team_id' => $this->eurPoolTeams[1]->id,
            'winner_id' => $winnerTeam->id,
            'completed' => true,
        ]);

        $processor = app(SeasonArchiveProcessor::class);
        $data = $this->makeTransitionData();

        $result = $processor->process($this->game, $data);

        $this->assertEquals(
            $winnerTeam->id,
            $result->getMetadata(SeasonTransitionData::META_UEL_WINNER),
            'SeasonArchiveProcessor should capture UEL winner from cup tie'
        );
    }

    public function test_archive_processor_picks_random_uel_entry_when_no_cup_tie(): void
    {
        // No UEL cup ties exist, but UEL entries do
        CompetitionEntry::updateOrCreate(
            [
                'game_id' => $this->game->id,
                'competition_id' => 'UEL',
                'team_id' => $this->eurPoolTeams[0]->id,
            ],
            ['entry_round' => 1]
        );

        $processor = app(SeasonArchiveProcessor::class);
        $data = $this->makeTransitionData();

        $result = $processor->process($this->game, $data);

        $this->assertNotNull(
            $result->getMetadata(SeasonTransitionData::META_UEL_WINNER),
            'SeasonArchiveProcessor should pick a random UEL entry when no cup tie exists'
        );
    }

    public function test_non_european_teams_are_not_selected_as_fillers(): void
    {
        // Create non-European teams with GamePlayer records (e.g. from World Cup)
        $nonEuropeanTeams = [];
        foreach (['BR', 'AR', 'MX', 'JP', 'KR'] as $country) {
            $team = Team::factory()->create(['country' => $country]);
            $nonEuropeanTeams[] = $team;
            $this->createPlayersForTeam($team, 99_999_999_99); // Very high value
        }

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();

        $processor->process($this->game, $data);

        $swissEntryTeamIds = CompetitionEntry::where('game_id', $this->game->id)
            ->whereIn('competition_id', ['UCL', 'UEL'])
            ->pluck('team_id')
            ->toArray();

        foreach ($nonEuropeanTeams as $team) {
            $this->assertNotContains(
                $team->id,
                $swissEntryTeamIds,
                "Non-European team {$team->country} should not be in any UEFA competition"
            );
        }
    }

    // =========================================
    // Cup winner + UEL winner edge cases
    // =========================================

    public function test_cup_winner_not_in_league_top_7_gets_uel_spot(): void
    {
        // Team at position 10 wins the Copa del Rey
        $cupWinner = $this->teamsByCountry['ES'][9]; // position 10
        $this->createCupFinal('ESPCUP', $cupWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $cupWinner->id)
                ->exists(),
            'Cup winner outside top 7 should qualify for UEL'
        );
    }

    public function test_cup_winner_already_in_ucl_cascades_uel_spot(): void
    {
        // Team at position 1 (already UCL via league) wins Copa del Rey
        $cupWinner = $this->teamsByCountry['ES'][0]; // position 1 = UCL
        $this->createCupFinal('ESPCUP', $cupWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        // Cup winner should be in UCL (via league), NOT in UEL
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $cupWinner->id)
                ->exists()
        );
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $cupWinner->id)
                ->exists()
        );

        // Position 8 (next non-qualified) should get the cascaded UEL spot
        $nextTeam = $this->teamsByCountry['ES'][7]; // position 8
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $nextTeam->id)
                ->exists(),
            'Cascaded UEL spot from cup winner should go to position 8'
        );
    }

    public function test_uel_winner_with_cup_uel_spot_only_appears_in_ucl(): void
    {
        // Team at position 10 wins Copa AND UEL
        $team = $this->teamsByCountry['ES'][9]; // position 10
        $this->createCupFinal('ESPCUP', $team->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $team->id);

        $processor->process($this->game, $data);

        // Should be in UCL (via UEL winner upgrade)
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UCL')
                ->where('team_id', $team->id)
                ->exists(),
            'Cup+UEL winner should be in UCL'
        );

        // Should NOT be in UEL (cup spot must cascade)
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $team->id)
                ->exists(),
            'Cup+UEL winner should NOT remain in UEL'
        );
    }

    public function test_uel_winner_vacated_uel_spot_cascades_to_next_team(): void
    {
        // Team at position 10 wins Copa AND UEL
        $team = $this->teamsByCountry['ES'][9]; // position 10
        $this->createCupFinal('ESPCUP', $team->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $team->id);

        $processor->process($this->game, $data);

        // The vacated UEL spot should go to the next non-qualified Spanish team.
        // Positions 1-5 = UCL, 6 = UEL, 7 = UECL, 8 = next non-qualified
        // But position 10 (the cup+UEL winner) is now in UCL, so position 8 gets UEL.
        $nextTeam = $this->teamsByCountry['ES'][7]; // position 8
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $nextTeam->id)
                ->exists(),
            'Vacated UEL spot should cascade to next non-qualified Spanish team'
        );
    }

    public function test_no_team_appears_in_multiple_european_competitions(): void
    {
        // Team at position 10 wins Copa AND UEL — complex scenario
        $team = $this->teamsByCountry['ES'][9];
        $this->createCupFinal('ESPCUP', $team->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $data->setMetadata(SeasonTransitionData::META_UEL_WINNER, $team->id);

        $processor->process($this->game, $data);

        $entries = CompetitionEntry::where('game_id', $this->game->id)
            ->whereIn('competition_id', ['UCL', 'UEL', 'UECL'])
            ->get()
            ->groupBy('team_id');

        $duplicates = $entries->filter(fn ($group) => $group->count() > 1);
        $this->assertTrue(
            $duplicates->isEmpty(),
            'No team should appear in multiple European competitions. Duplicates: ' .
            $duplicates->map(fn ($group, $teamId) => $teamId . ' in ' . $group->pluck('competition_id')->implode(', '))->implode('; ')
        );
    }

    // =========================================
    // Filler team edge cases
    // =========================================

    public function test_relegated_team_is_not_filler_in_european_competitions(): void
    {
        // Register a Spanish team (position 15) in the EUR pool so it could be a filler
        $relegatedTeam = $this->teamsByCountry['ES'][14]; // position 15
        CompetitionTeam::create([
            'competition_id' => 'EUR',
            'team_id' => $relegatedTeam->id,
            'season' => '2025',
        ]);

        // Simulate relegation: move team to ESP2
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESP2',
            'team_id' => $relegatedTeam->id,
            'entry_round' => 1,
        ]);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $inEuropean = CompetitionEntry::where('game_id', $this->game->id)
            ->whereIn('competition_id', ['UCL', 'UEL', 'UECL'])
            ->where('team_id', $relegatedTeam->id)
            ->exists();

        $this->assertFalse($inEuropean, 'A team from a configured country should never be a filler');
    }

    public function test_configured_country_teams_never_used_as_fillers(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $configuredCountries = ['ES', 'EN', 'DE', 'IT', 'FR'];

        foreach (['UCL', 'UEL', 'UECL'] as $competitionId) {
            $entries = CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', $competitionId)
                ->pluck('team_id')
                ->toArray();

            // Get teams from configured countries that are in this competition
            $configuredTeamsInComp = Team::whereIn('id', $entries)
                ->whereIn('country', $configuredCountries)
                ->pluck('id')
                ->toArray();

            // Every configured-country team in the competition must have qualified
            // via league standings (not as a filler). Check they're in qualifying positions.
            foreach ($configuredTeamsInComp as $teamId) {
                $team = Team::find($teamId);
                $slots = $this->countryConfig->continentalSlots($team->country);
                $qualifyingTeamIds = [];
                foreach ($slots as $leagueId => $allocations) {
                    foreach ($allocations as $continentalId => $positions) {
                        if ($continentalId === $competitionId) {
                            foreach ($positions as $pos) {
                                $qualifyingTeamIds[] = $this->teamsByCountry[$team->country][$pos - 1]->id ?? null;
                            }
                        }
                    }
                }

                $this->assertContains(
                    $teamId,
                    $qualifyingTeamIds,
                    "Team {$teamId} from {$team->country} in {$competitionId} should have qualified via standings, not as a filler"
                );
            }
        }
    }

    public function test_uecl_has_36_entries_after_qualification(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        $ueclCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UECL')
            ->count();

        $this->assertEquals(36, $ueclCount, "UECL should have exactly 36 entries, got {$ueclCount}");
    }

    // =========================================
    // Cup winner + UECL upgrade edge cases
    // =========================================

    public function test_cup_winner_in_uecl_via_league_gets_upgraded_to_uel(): void
    {
        // Team at position 7 (UECL via league) wins Copa del Rey
        $cupWinner = $this->teamsByCountry['ES'][6]; // position 7 = UECL
        $this->createCupFinal('ESPCUP', $cupWinner->id);

        $processor = app(UefaQualificationProcessor::class);
        $data = $this->makeTransitionData();
        $processor->process($this->game, $data);

        // Should be upgraded to UEL
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UEL')
                ->where('team_id', $cupWinner->id)
                ->exists(),
            'Cup winner in UECL via league should be upgraded to UEL'
        );

        // Should NOT be in UECL anymore
        $this->assertFalse(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UECL')
                ->where('team_id', $cupWinner->id)
                ->exists(),
            'Cup winner should no longer be in UECL after upgrade'
        );

        // Vacated UECL spot should cascade to position 8
        $nextTeam = $this->teamsByCountry['ES'][7]; // position 8
        $this->assertTrue(
            CompetitionEntry::where('game_id', $this->game->id)
                ->where('competition_id', 'UECL')
                ->where('team_id', $nextTeam->id)
                ->exists(),
            'Vacated UECL spot should cascade to position 8'
        );
    }

    // =========================================
    // Helpers
    // =========================================

    private function makeTransitionData(): SeasonTransitionData
    {
        return new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );
    }

    private function createCupFinal(string $cupId, string $winnerId): void
    {
        $loser = $this->eurPoolTeams[0]; // arbitrary opponent
        CupTie::create([
            'game_id' => $this->game->id,
            'competition_id' => $cupId,
            'round_number' => 7, // cup_final_round from config
            'home_team_id' => $winnerId,
            'away_team_id' => $loser->id,
            'winner_id' => $winnerId,
            'completed' => true,
        ]);
    }

    private function createCountryTeamsWithStandings(string $country, string $competitionId, int $count): void
    {
        $teams = [];
        for ($i = 0; $i < $count; $i++) {
            $team = Team::factory()->create(['country' => $country]);
            $teams[] = $team;

            // Create standings
            GameStanding::create([
                'game_id' => $this->game->id,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'position' => $i + 1,
                'played' => 38,
                'won' => max(0, 20 - $i),
                'drawn' => 10,
                'lost' => max(0, $i),
                'goals_for' => max(10, 60 - $i * 2),
                'goals_against' => 20 + $i,
                'points' => max(0, (20 - $i) * 3 + 10),
            ]);

            // Create GamePlayer records for each team
            $this->createPlayersForTeam($team, 10_000_000_00 - ($i * 200_000_00));
        }

        $this->teamsByCountry[$country] = $teams;
    }

    private function createPlayersForTeam(Team $team, int $marketValue): void
    {
        $player = Player::factory()->create();
        GamePlayer::factory()->create([
            'game_id' => $this->game->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'market_value_cents' => $marketValue,
        ]);
    }

    /**
     * Seed initial continental entries that mimic the game setup state.
     * Places configured-country teams + EUR pool teams to reach ~36 per competition.
     */
    private function seedInitialContinentalEntries(): void
    {
        // Group entries by competition from configured countries
        $byCompetition = []; // competitionId => [teamIds]

        foreach (['ES', 'EN', 'DE', 'IT', 'FR'] as $country) {
            $slots = $this->countryConfig->continentalSlots($country);
            foreach ($slots as $leagueId => $allocations) {
                foreach ($allocations as $continentalId => $positions) {
                    foreach ($positions as $pos) {
                        $teamIndex = $pos - 1;
                        if (!isset($this->teamsByCountry[$country][$teamIndex])) {
                            continue;
                        }
                        $byCompetition[$continentalId][] = $this->teamsByCountry[$country][$teamIndex]->id;
                    }
                }
            }
        }

        // Add configured-country teams to each competition
        foreach ($byCompetition as $competitionId => $teamIds) {
            foreach ($teamIds as $teamId) {
                CompetitionEntry::create([
                    'game_id' => $this->game->id,
                    'competition_id' => $competitionId,
                    'team_id' => $teamId,
                    'entry_round' => 1,
                ]);
            }
        }

        // Fill remaining slots with EUR pool teams
        $poolIndex = 0;
        foreach (['UCL', 'UEL', 'UECL'] as $competitionId) {
            $needed = 36 - count($byCompetition[$competitionId] ?? []);
            for ($i = 0; $i < $needed && $poolIndex < count($this->eurPoolTeams); $i++) {
                CompetitionEntry::create([
                    'game_id' => $this->game->id,
                    'competition_id' => $competitionId,
                    'team_id' => $this->eurPoolTeams[$poolIndex]->id,
                    'entry_round' => 1,
                ]);
                $poolIndex++;
            }
        }
    }
}
