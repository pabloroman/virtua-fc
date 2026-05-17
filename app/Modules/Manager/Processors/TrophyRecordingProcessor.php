<?php

namespace App\Modules\Manager\Processors;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\ManagerStats;
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
        return 10;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $created = 0;
        $created += $this->recordLeagueTitle($game);
        $created += $this->recordCupWins($game);

        if ($created > 0) {
            // Keep the denormalized leaderboard counter in sync. No-op if no
            // manager_stats row exists yet (first match hasn't been played);
            // the row will be created against the live trophies via the
            // rebuilder or the listener's next write.
            ManagerStats::where('game_id', $game->id)->increment('trophies_count', $created);
        }

        return $data;
    }

    private function recordLeagueTitle(Game $game): int
    {
        $standing = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->where('position', 1)
            ->first();

        if (! $standing) {
            return 0;
        }

        $trophy = ManagerTrophy::firstOrCreate([
            'game_id' => $game->id,
            'competition_id' => $game->competition_id,
            'season' => $game->season,
        ], [
            'user_id' => $game->user_id,
            'team_id' => $game->team_id,
            'trophy_type' => 'league',
        ]);

        return $trophy->wasRecentlyCreated ? 1 : 0;
    }

    private function recordCupWins(Game $game): int
    {
        $supercupIds = $this->getSupercupCompetitionIds();

        $entries = CompetitionEntry::with('competition')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('competition_id', '!=', $game->competition_id)
            ->get();

        $created = 0;

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

            $trophy = ManagerTrophy::firstOrCreate([
                'game_id' => $game->id,
                'competition_id' => $competition->id,
                'season' => $game->season,
            ], [
                'user_id' => $game->user_id,
                'team_id' => $game->team_id,
                'trophy_type' => $trophyType,
            ]);

            if ($trophy->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function determineTrophyType(Competition $competition, array $supercupIds): string
    {
        // UEFA Super Cup is a continental supercup — no country-level
        // supercup config entry maps to it, so tag it explicitly.
        if ($competition->id === 'UEFASUP' || in_array($competition->id, $supercupIds)) {
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
