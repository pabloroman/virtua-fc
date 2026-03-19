<?php

namespace App\Modules\Manager\Processors;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\ManagerTrophy;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;

/**
 * Records trophies won by the player during the closing season.
 * Priority: 4 (runs before SeasonArchiveProcessor at 5, so cup_ties data still exists)
 */
class TrophyRecordingProcessor implements SeasonProcessor
{
    public function priority(): int
    {
        return 4;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $this->recordLeagueTitle($game);
        $this->recordCupWins($game);

        return $data;
    }

    private function recordLeagueTitle(Game $game): void
    {
        $standing = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->where('position', 1)
            ->first();

        if (! $standing) {
            return;
        }

        ManagerTrophy::firstOrCreate([
            'game_id' => $game->id,
            'competition_id' => $game->competition_id,
            'season' => $game->season,
        ], [
            'user_id' => $game->user_id,
            'team_id' => $game->team_id,
            'trophy_type' => 'league',
        ]);
    }

    private function recordCupWins(Game $game): void
    {
        $supercupIds = $this->getSupercupCompetitionIds();

        $entries = CompetitionEntry::with('competition')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('competition_id', '!=', $game->competition_id)
            ->get();

        foreach ($entries as $entry) {
            $competition = $entry->competition;

            // Find the final round: the completed tie with the highest round_number
            $finalTie = CupTie::where('game_id', $game->id)
                ->where('competition_id', $competition->id)
                ->where('completed', true)
                ->orderByDesc('round_number')
                ->first();

            if (! $finalTie || $finalTie->winner_id !== $game->team_id) {
                continue;
            }

            $trophyType = $this->determineTrophyType($competition, $supercupIds);

            ManagerTrophy::firstOrCreate([
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'season' => $game->season,
            ], [
                'user_id' => $game->user_id,
                'team_id' => $game->team_id,
                'trophy_type' => $trophyType,
            ]);
        }
    }

    private function determineTrophyType(Competition $competition, array $supercupIds): string
    {
        if (in_array($competition->id, $supercupIds)) {
            return 'supercup';
        }

        return match ($competition->role) {
            Competition::ROLE_EUROPEAN => 'european',
            Competition::ROLE_DOMESTIC_CUP => 'cup',
            default => 'league',
        };
    }

    private function getSupercupCompetitionIds(): array
    {
        $ids = [];

        foreach (config('countries') as $country) {
            if (isset($country['supercup']['competition'])) {
                $ids[] = $country['supercup']['competition'];
            }
        }

        return $ids;
    }
}
