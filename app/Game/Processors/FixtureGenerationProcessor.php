<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\CupTie;
use App\Models\FixtureTemplate;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Support\Str;

/**
 * Generates fixtures for the new season.
 * Priority: 30 (runs third)
 */
class FixtureGenerationProcessor implements SeasonEndProcessor
{
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

        // Copy new fixtures from templates
        $this->copyFixturesToGame($game->id, $data->competitionId, $data->newSeason, $data->oldSeason);

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

    /**
     * Copy fixture templates to game-specific matches.
     * First tries new season templates, then falls back to old season with date adjustment.
     */
    private function copyFixturesToGame(string $gameId, string $competitionId, string $newSeason, string $oldSeason): void
    {
        // Try to get fixtures for the new season
        $fixtures = FixtureTemplate::where('competition_id', $competitionId)
            ->where('season', $newSeason)
            ->get();

        // If no fixtures for new season, copy from old season with adjusted dates
        if ($fixtures->isEmpty()) {
            $fixtures = FixtureTemplate::where('competition_id', $competitionId)
                ->where('season', $oldSeason)
                ->get();

            $yearDiff = $this->calculateYearDifference($oldSeason, $newSeason);

            foreach ($fixtures as $fixture) {
                GameMatch::create([
                    'id' => Str::uuid()->toString(),
                    'game_id' => $gameId,
                    'competition_id' => $competitionId,
                    'round_number' => $fixture->round_number,
                    'round_name' => "Matchday {$fixture->round_number}",
                    'home_team_id' => $fixture->home_team_id,
                    'away_team_id' => $fixture->away_team_id,
                    'scheduled_date' => $fixture->scheduled_date->addYears($yearDiff),
                    'home_score' => null,
                    'away_score' => null,
                    'played' => false,
                ]);
            }
        } else {
            foreach ($fixtures as $fixture) {
                GameMatch::create([
                    'id' => Str::uuid()->toString(),
                    'game_id' => $gameId,
                    'competition_id' => $competitionId,
                    'round_number' => $fixture->round_number,
                    'round_name' => "Matchday {$fixture->round_number}",
                    'home_team_id' => $fixture->home_team_id,
                    'away_team_id' => $fixture->away_team_id,
                    'scheduled_date' => $fixture->scheduled_date,
                    'home_score' => null,
                    'away_score' => null,
                    'played' => false,
                ]);
            }
        }
    }

    /**
     * Calculate the year difference between seasons.
     */
    private function calculateYearDifference(string $oldSeason, string $newSeason): int
    {
        // Handle formats like "2024" or "2024-25"
        $oldYear = (int) explode('-', $oldSeason)[0];
        $newYear = (int) explode('-', $newSeason)[0];

        return $newYear - $oldYear;
    }
}
