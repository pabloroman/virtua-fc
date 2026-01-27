<?php

namespace App\Game\Services;

use App\Models\GameMatch;
use App\Models\GamePlayer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LineupService
{
    /**
     * Position requirements for a balanced XI.
     * 1 GK + 4 DEF + 4 MID + 2 FWD
     */
    private const FORMATION = [
        'Goalkeeper' => 1,
        'Defender' => 4,
        'Midfielder' => 4,
        'Forward' => 2,
    ];

    /**
     * Get available players (not injured/suspended) for a team.
     */
    public function getAvailablePlayers(string $gameId, string $teamId, Carbon $matchDate, int $matchday): Collection
    {
        return GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get()
            ->filter(fn (GamePlayer $player) => $player->isAvailable($matchDate, $matchday));
    }

    /**
     * Get all players for a team (including unavailable, for display purposes).
     */
    public function getAllPlayers(string $gameId, string $teamId): Collection
    {
        return GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get();
    }

    /**
     * Validate lineup: 11 players, all available, correct team.
     */
    public function validateLineup(
        array $playerIds,
        string $gameId,
        string $teamId,
        Carbon $matchDate,
        int $matchday
    ): array {
        $errors = [];

        if (count($playerIds) !== 11) {
            $errors[] = 'You must select exactly 11 players.';
            return $errors;
        }

        if (count($playerIds) !== count(array_unique($playerIds))) {
            $errors[] = 'Duplicate players detected.';
            return $errors;
        }

        $availablePlayers = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $matchday);
        $availableIds = $availablePlayers->pluck('id')->toArray();

        foreach ($playerIds as $playerId) {
            if (!in_array($playerId, $availableIds)) {
                $errors[] = 'One or more selected players are not available.';
                break;
            }
        }

        // Check for at least 1 goalkeeper
        $selectedPlayers = $availablePlayers->filter(fn ($p) => in_array($p->id, $playerIds));
        $goalkeepers = $selectedPlayers->filter(fn ($p) => $p->position === 'Goalkeeper');

        if ($goalkeepers->isEmpty()) {
            $errors[] = 'You must include at least one goalkeeper.';
        }

        return $errors;
    }

    /**
     * Auto-select best XI by overall_score, respecting positions.
     * Algorithm: 1 GK + 4 DEF + 4 MID + 2 FWD (sorted by overall desc)
     */
    public function autoSelectLineup(string $gameId, string $teamId, Carbon $matchDate, int $matchday): array
    {
        $available = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $matchday);

        $lineup = [];

        // Group players by position category
        $grouped = $available->groupBy(fn ($p) => $p->position_group);

        // Select players for each position group
        foreach (self::FORMATION as $positionGroup => $count) {
            $positionPlayers = ($grouped->get($positionGroup) ?? collect())
                ->sortByDesc('overall_score')
                ->take($count);

            foreach ($positionPlayers as $player) {
                $lineup[] = $player->id;
            }
        }

        // If we don't have enough for standard formation, fill with best available
        if (count($lineup) < 11) {
            $remaining = $available
                ->filter(fn ($p) => !in_array($p->id, $lineup))
                ->sortByDesc('overall_score');

            foreach ($remaining as $player) {
                if (count($lineup) >= 11) {
                    break;
                }
                $lineup[] = $player->id;
            }
        }

        return $lineup;
    }

    /**
     * Save lineup to match record.
     */
    public function saveLineup(GameMatch $match, string $teamId, array $playerIds): void
    {
        if ($match->home_team_id === $teamId) {
            $match->home_lineup = $playerIds;
        } elseif ($match->away_team_id === $teamId) {
            $match->away_lineup = $playerIds;
        }

        $match->save();
    }

    /**
     * Check if a match needs lineup selection for a given team.
     */
    public function needsLineup(GameMatch $match, string $teamId): bool
    {
        if ($match->played) {
            return false;
        }

        // Check if lineup is already set
        if ($match->home_team_id === $teamId && !empty($match->home_lineup)) {
            return false;
        }

        if ($match->away_team_id === $teamId && !empty($match->away_lineup)) {
            return false;
        }

        // Only need lineup if team has players available
        // (edge case: tests or new games without players)
        $playerCount = GamePlayer::where('game_id', $match->game_id)
            ->where('team_id', $teamId)
            ->count();

        return $playerCount > 0;
    }

    /**
     * Get the lineup for a team from a match.
     */
    public function getLineup(GameMatch $match, string $teamId): ?array
    {
        if ($match->home_team_id === $teamId) {
            return $match->home_lineup;
        }

        if ($match->away_team_id === $teamId) {
            return $match->away_lineup;
        }

        return null;
    }

    /**
     * Get lineup players as a collection.
     */
    public function getLineupPlayers(string $gameId, array $playerIds): Collection
    {
        if (empty($playerIds)) {
            return collect();
        }

        return GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->whereIn('id', $playerIds)
            ->get();
    }
}
