<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\LeagueFixtureGenerator;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupRoundTemplate;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Generates fixtures for the new season using the round-robin algorithm.
 *
 * Loads the matchday calendar from schedule.json (always from the base season data),
 * adjusts dates for the new season, and generates fixtures from the current
 * competition team roster. Also regenerates cup_round_templates with adjusted dates.
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
        $this->generateLeagueFixtures($game->id, $data->competitionId, $data->newSeason);

        // Regenerate cup round templates with dates adjusted for the new season
        $this->regenerateCupRoundTemplates($data->newSeason);

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

    private function generateLeagueFixtures(string $gameId, string $competitionId, string $newSeason): void
    {
        // Always load from the base season (data/2025/) and adjust dates forward
        $competition = Competition::find($competitionId);
        $baseSeason = $competition->season;

        $matchdays = LeagueFixtureGenerator::loadMatchdays($competitionId, $baseSeason);
        $yearDiff = $this->calculateYearDifference($baseSeason, $newSeason);

        if ($yearDiff !== 0) {
            $matchdays = LeagueFixtureGenerator::adjustMatchdayYears($matchdays, $yearDiff);
        }

        // Get team IDs from the game roster (already updated by PromotionRelegationProcessor)
        $teamIds = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
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
                'scheduled_date' => Carbon::parse($fixture['date']),
                'home_score' => null,
                'away_score' => null,
                'played' => false,
            ]);
        }
    }

    /**
     * Regenerate cup_round_templates for all competitions with knockout rounds.
     *
     * Reads the knockout section from each competition's base season schedule.json,
     * adjusts dates by the year difference, and replaces existing templates.
     */
    private function regenerateCupRoundTemplates(string $newSeason): void
    {
        CupRoundTemplate::query()->delete();

        $competitions = Competition::all();

        foreach ($competitions as $competition) {
            $schedulePath = base_path("data/{$competition->season}/{$competition->id}/schedule.json");
            if (!file_exists($schedulePath)) {
                continue;
            }

            $scheduleData = json_decode(file_get_contents($schedulePath), true);
            $knockoutRounds = $scheduleData['knockout'] ?? [];
            if (empty($knockoutRounds)) {
                continue;
            }

            $yearDiff = $this->calculateYearDifference($competition->season, $newSeason);

            foreach ($knockoutRounds as $round) {
                $hasTwoLegs = isset($round['second_leg_date']);
                $firstLegDate = $hasTwoLegs ? $round['first_leg_date'] : ($round['date'] ?? null);
                $secondLegDate = $hasTwoLegs ? $round['second_leg_date'] : null;

                if ($firstLegDate && $yearDiff !== 0) {
                    $firstLegDate = Carbon::parse($firstLegDate)->addYears($yearDiff)->format('Y-m-d');
                }
                if ($secondLegDate && $yearDiff !== 0) {
                    $secondLegDate = Carbon::parse($secondLegDate)->addYears($yearDiff)->format('Y-m-d');
                }

                CupRoundTemplate::create([
                    'competition_id' => $competition->id,
                    'season' => $newSeason,
                    'round_number' => $round['round'],
                    'round_name' => $round['name'],
                    'type' => $hasTwoLegs ? 'two_leg' : 'one_leg',
                    'first_leg_date' => $firstLegDate,
                    'second_leg_date' => $secondLegDate,
                    'teams_entering' => 0,
                ]);
            }
        }
    }

    private function calculateYearDifference(string $oldSeason, string $newSeason): int
    {
        $oldYear = (int) explode('-', $oldSeason)[0];
        $newYear = (int) explode('-', $newSeason)[0];

        return $newYear - $oldYear;
    }
}
