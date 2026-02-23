<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
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

        // Create EUR pool teams (non-configured countries) — enough to fill both UCL and UEL
        $eurCountries = ['PT', 'NL', 'BE', 'TR', 'GR', 'AT', 'PL', 'CZ', 'RO', 'RS', 'HR', 'NO', 'CH', 'IL', 'DK', 'SE', 'UA', 'SC'];
        foreach ($eurCountries as $i => $country) {
            $team = Team::factory()->create(['country' => $country]);
            $this->eurPoolTeams[] = $team;

            // Create GamePlayer records so fillRemainingContinentalSlots can find them
            $this->createPlayersForTeam($team, 5_000_000_00 - ($i * 100_000_00));
        }

        // Seed initial CompetitionEntry records for UCL and UEL
        // (mimicking what the initial game setup would create)
        $this->seedInitialContinentalEntries();
    }

    public function test_ucl_has_36_entries_after_qualification(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );

        $processor->process($this->game, $data);

        $uclCount = CompetitionEntry::where('game_id', $this->game->id)
            ->where('competition_id', 'UCL')
            ->count();

        $this->assertEquals(36, $uclCount, "UCL should have exactly 36 entries, got {$uclCount}");
    }

    public function test_uel_has_36_entries_after_qualification(): void
    {
        $processor = app(UefaQualificationProcessor::class);
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );

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

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );
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

        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );
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
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );

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
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );

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
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );

        $result = $processor->process($this->game, $data);

        $this->assertNotNull(
            $result->getMetadata(SeasonTransitionData::META_UEL_WINNER),
            'SeasonArchiveProcessor should pick a random UEL entry when no cup tie exists'
        );
    }

    // =========================================
    // Helpers
    // =========================================

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
        // UCL entries from configured countries based on continental_slots
        $uclFromConfig = [];
        $uelFromConfig = [];

        foreach (['ES', 'EN', 'DE', 'IT', 'FR'] as $country) {
            $slots = $this->countryConfig->continentalSlots($country);
            foreach ($slots as $leagueId => $allocations) {
                foreach ($allocations as $continentalId => $positions) {
                    foreach ($positions as $pos) {
                        $teamIndex = $pos - 1;
                        if (!isset($this->teamsByCountry[$country][$teamIndex])) {
                            continue;
                        }
                        $teamId = $this->teamsByCountry[$country][$teamIndex]->id;

                        if ($continentalId === 'UCL') {
                            $uclFromConfig[] = $teamId;
                        } elseif ($continentalId === 'UEL') {
                            $uelFromConfig[] = $teamId;
                        }
                    }
                }
            }
        }

        // Add configured-country teams
        foreach ($uclFromConfig as $teamId) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UCL',
                'team_id' => $teamId,
                'entry_round' => 1,
            ]);
        }

        foreach ($uelFromConfig as $teamId) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UEL',
                'team_id' => $teamId,
                'entry_round' => 1,
            ]);
        }

        // Fill remaining slots with EUR pool teams
        $uclNeeded = 36 - count($uclFromConfig);
        $uelNeeded = 36 - count($uelFromConfig);
        $poolIndex = 0;

        for ($i = 0; $i < $uclNeeded && $poolIndex < count($this->eurPoolTeams); $i++) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UCL',
                'team_id' => $this->eurPoolTeams[$poolIndex]->id,
                'entry_round' => 1,
            ]);
            $poolIndex++;
        }

        for ($i = 0; $i < $uelNeeded && $poolIndex < count($this->eurPoolTeams); $i++) {
            CompetitionEntry::create([
                'game_id' => $this->game->id,
                'competition_id' => 'UEL',
                'team_id' => $this->eurPoolTeams[$poolIndex]->id,
                'entry_round' => 1,
            ]);
            $poolIndex++;
        }
    }
}
