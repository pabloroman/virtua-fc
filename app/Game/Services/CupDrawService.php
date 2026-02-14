<?php

namespace App\Game\Services;

use App\Game\DTO\PlayoffRoundConfig;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\GameMatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CupDrawService
{
    /**
     * Conduct a draw for a specific cup round.
     *
     * @return Collection<CupTie>
     */
    public function conductDraw(string $gameId, string $competitionId, int $roundNumber): Collection
    {
        $roundConfig = $this->getRoundConfig($competitionId, $roundNumber);

        if (!$roundConfig) {
            throw new \RuntimeException("No knockout round config found for {$competitionId} round {$roundNumber}");
        }

        $competition = Competition::find($competitionId);
        $season = $competition?->season ?? '2025';

        // Get all teams eligible for this round
        $teams = $this->getTeamsForRound($gameId, $competitionId, $season, $roundNumber);

        // Shuffle teams for random pairing
        $shuffledTeams = $teams->shuffle();

        // Create ties (pairings)
        $ties = collect();
        $teamCount = $shuffledTeams->count();

        for ($i = 0; $i < $teamCount; $i += 2) {
            if ($i + 1 >= $teamCount) {
                // Odd number of teams - one gets a bye (shouldn't happen in real cup)
                break;
            }

            $homeTeamId = $shuffledTeams[$i];
            $awayTeamId = $shuffledTeams[$i + 1];

            // Create the cup tie
            $tie = CupTie::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'round_number' => $roundNumber,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
            ]);

            // Create first leg match
            $firstLegMatch = GameMatch::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'competition_id' => $competitionId,
                'round_number' => $roundNumber,
                'round_name' => $roundConfig->name,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'scheduled_date' => $roundConfig->firstLegDate,
                'cup_tie_id' => $tie->id,
            ]);

            $tie->update(['first_leg_match_id' => $firstLegMatch->id]);

            // Create second leg match if two-legged
            if ($roundConfig->twoLegged) {
                $secondLegMatch = GameMatch::create([
                    'id' => Str::uuid()->toString(),
                    'game_id' => $gameId,
                    'competition_id' => $competitionId,
                    'round_number' => $roundNumber,
                    'round_name' => $roundConfig->name . ' (Vuelta)',
                    'home_team_id' => $awayTeamId, // Teams swap for second leg
                    'away_team_id' => $homeTeamId,
                    'scheduled_date' => $roundConfig->secondLegDate,
                    'cup_tie_id' => $tie->id,
                ]);

                $tie->update(['second_leg_match_id' => $secondLegMatch->id]);
            }

            $ties->push($tie->fresh());
        }

        return $ties;
    }

    /**
     * Get all team IDs eligible for a specific round.
     *
     * @return Collection<string>
     */
    private function getTeamsForRound(string $gameId, string $competitionId, string $season, int $roundNumber): Collection
    {
        $teams = collect();

        // Teams entering at this specific round
        $enteringTeams = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('entry_round', $roundNumber)
            ->pluck('team_id');

        $teams = $teams->merge($enteringTeams);

        // Winners from previous round
        if ($roundNumber > 1) {
            $previousWinners = CupTie::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->where('round_number', $roundNumber - 1)
                ->where('completed', true)
                ->whereNotNull('winner_id')
                ->pluck('winner_id');

            $teams = $teams->merge($previousWinners);
        }

        return $teams->unique()->values();
    }

    /**
     * Check if a draw is needed for a specific round.
     */
    public function needsDrawForRound(string $gameId, string $competitionId, int $roundNumber): bool
    {
        // Check if ties already exist for this round
        $existingTies = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $roundNumber)
            ->count();

        if ($existingTies > 0) {
            return false;
        }

        // Check if round config exists in schedule.json
        $roundConfig = $this->getRoundConfig($competitionId, $roundNumber);

        if (!$roundConfig) {
            return false;
        }

        // For round 1, we just need teams entering at round 1
        if ($roundNumber === 1) {
            $teamsEntering = CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $competitionId)
                ->where('entry_round', 1)
                ->count();

            return $teamsEntering > 0;
        }

        // For later rounds, all previous round ties must be completed
        $previousRoundQuery = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('round_number', $roundNumber - 1);

        $totalPreviousTies = $previousRoundQuery->count();

        if ($totalPreviousTies === 0) {
            return false;
        }

        $completedPreviousTies = (clone $previousRoundQuery)->where('completed', true)->count();

        return $totalPreviousTies === $completedPreviousTies;
    }

    /**
     * Get the next round that needs a draw.
     */
    public function getNextRoundNeedingDraw(string $gameId, string $competitionId): ?int
    {
        // Find rounds that already have ties drawn
        $drawnRounds = CupTie::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->distinct()
            ->pluck('round_number');

        // Load all knockout rounds from schedule.json and find the first undrawn
        $allRounds = $this->getAllRoundConfigs($competitionId);

        $nextUndrawnRound = null;
        foreach ($allRounds as $round) {
            if (!$drawnRounds->contains($round->round)) {
                $nextUndrawnRound = $round->round;
                break;
            }
        }

        if ($nextUndrawnRound === null) {
            return null;
        }

        // Verify it's actually ready (previous round complete or it's round 1)
        if ($this->needsDrawForRound($gameId, $competitionId, $nextUndrawnRound)) {
            return $nextUndrawnRound;
        }

        return null;
    }

    /**
     * Get round config from schedule.json for a specific round.
     */
    private function getRoundConfig(string $competitionId, int $roundNumber): ?PlayoffRoundConfig
    {
        $rounds = $this->getAllRoundConfigs($competitionId);

        foreach ($rounds as $round) {
            if ($round->round === $roundNumber) {
                return $round;
            }
        }

        return null;
    }

    /**
     * Get all knockout round configs for a competition from schedule.json.
     *
     * @return PlayoffRoundConfig[]
     */
    private function getAllRoundConfigs(string $competitionId): array
    {
        $competition = Competition::find($competitionId);
        if (!$competition) {
            return [];
        }

        return LeagueFixtureGenerator::loadKnockoutRounds($competitionId, $competition->season);
    }
}
