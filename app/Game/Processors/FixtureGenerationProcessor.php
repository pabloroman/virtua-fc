<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\LeagueFixtureGenerator;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Generates fixtures for the new season using the round-robin algorithm.
 *
 * Loads the matchday calendar from matchdays.json, adjusts dates for the new season,
 * and generates fixtures from the current competition team roster.
 *
 * Priority: 30 (runs after promotion/relegation at 26)
 */
class FixtureGenerationProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly LeagueFixtureGenerator $generator,
    ) {}

    public function priority(): int
    {
        return 30;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Delete old matches
        GameMatch::where('game_id', $game->id)->delete();

        // Delete old cup ties
        CupTie::where('game_id', $game->id)->delete();

        // Generate new league fixtures
        $this->generateLeagueFixtures($game->id, $data->competitionId, $data->oldSeason, $data->newSeason);

        // Update current date to first fixture
        $firstMatch = GameMatch::where('game_id', $game->id)
            ->orderBy('scheduled_date')
            ->first();

        if ($firstMatch) {
            $game->update([
                'current_date' => $firstMatch->scheduled_date->toDateString(),
            ]);
        }

        return $data;
    }

    private function generateLeagueFixtures(string $gameId, string $competitionId, string $oldSeason, string $newSeason): void
    {
        // Load matchday calendar and adjust dates for the new season
        $matchdays = LeagueFixtureGenerator::loadMatchdays($competitionId, $oldSeason);
        $yearDiff = $this->calculateYearDifference($oldSeason, $newSeason);

        if ($yearDiff !== 0) {
            $matchdays = LeagueFixtureGenerator::adjustMatchdayYears($matchdays, $yearDiff);
        }

        // Get team IDs from the current roster (already updated by PromotionRelegationProcessor)
        $teamIds = CompetitionTeam::where('competition_id', $competitionId)
            ->where('season', $newSeason)
            ->pluck('team_id')
            ->toArray();

        $fixtures = $this->generator->generate($teamIds, $matchdays);

        foreach ($fixtures as $fixture) {
            GameMatch::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'round_number' => $fixture['matchday'],
                'home_team_id' => $fixture['homeTeamId'],
                'away_team_id' => $fixture['awayTeamId'],
                'scheduled_date' => Carbon::createFromFormat('d/m/y', $fixture['date']),
                'home_score' => null,
                'away_score' => null,
                'played' => false,
            ]);
        }
    }

    private function calculateYearDifference(string $oldSeason, string $newSeason): int
    {
        $oldYear = (int) explode('-', $oldSeason)[0];
        $newYear = (int) explode('-', $newSeason)[0];

        return $newYear - $oldYear;
    }
}
