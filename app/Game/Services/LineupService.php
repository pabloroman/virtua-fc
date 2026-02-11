<?php

namespace App\Game\Services;

use App\Game\Enums\Formation;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LineupService
{

    /**
     * Get available players (not injured/suspended) for a team.
     * Batch loads suspensions to avoid N+1 queries.
     */
    public function getAvailablePlayers(string $gameId, string $teamId, Carbon $matchDate, string $competitionId): Collection
    {
        $players = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get();

        // Batch load suspended player IDs for this competition (single query)
        $suspendedPlayerIds = PlayerSuspension::where('competition_id', $competitionId)
            ->where('matches_remaining', '>', 0)
            ->whereIn('game_player_id', $players->pluck('id'))
            ->pluck('game_player_id')
            ->toArray();

        // Filter in memory using pre-loaded suspension data
        return $players->filter(function (GamePlayer $player) use ($matchDate, $suspendedPlayerIds) {
            // Check if suspended (using pre-loaded IDs)
            if (in_array($player->id, $suspendedPlayerIds)) {
                return false;
            }
            // Check injury
            if ($player->injury_until && $matchDate && $player->injury_until->gt($matchDate)) {
                return false;
            }
            return true;
        });
    }

    /**
     * Get all players for a team (including unavailable, for display purposes).
     */
    public function getAllPlayers(string $gameId, string $teamId): Collection
    {
        return GamePlayer::with(['player', 'suspensions'])
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get();
    }

    /**
     * Get all players for a team, sorted and grouped by position.
     *
     * @return array{goalkeepers: Collection, defenders: Collection, midfielders: Collection, forwards: Collection, all: Collection}
     */
    public function getPlayersByPositionGroup(string $gameId, string $teamId): array
    {
        $allPlayers = $this->getAllPlayers($gameId, $teamId);

        $grouped = $allPlayers
            ->sortBy(fn ($p) => $this->positionSortOrder($p->position))
            ->groupBy(fn ($p) => $p->position_group);

        return [
            'goalkeepers' => $grouped->get('Goalkeeper', collect()),
            'defenders' => $grouped->get('Defender', collect()),
            'midfielders' => $grouped->get('Midfielder', collect()),
            'forwards' => $grouped->get('Forward', collect()),
            'all' => $allPlayers,
        ];
    }

    /**
     * Get sort order for positions within their group.
     */
    private function positionSortOrder(string $position): int
    {
        return match ($position) {
            'Goalkeeper' => 1,
            'Centre-Back' => 10,
            'Left-Back' => 11,
            'Right-Back' => 12,
            'Defensive Midfield' => 20,
            'Central Midfield' => 21,
            'Left Midfield' => 22,
            'Right Midfield' => 23,
            'Attacking Midfield' => 24,
            'Left Winger' => 30,
            'Right Winger' => 31,
            'Second Striker' => 32,
            'Centre-Forward' => 33,
            default => 99,
        };
    }

    /**
     * Validate lineup: 11 players, all available, correct positions for formation.
     */
    public function validateLineup(
        array $playerIds,
        string $gameId,
        string $teamId,
        Carbon $matchDate,
        string $competitionId,
        ?Formation $formation = null
    ): array {
        $formation = $formation ?? Formation::F_4_4_2;
        $requirements = $formation->requirements();
        $errors = [];

        if (count($playerIds) !== 11) {
            $errors[] = 'You must select exactly 11 players.';
            return $errors;
        }

        if (count($playerIds) !== count(array_unique($playerIds))) {
            $errors[] = 'Duplicate players detected.';
            return $errors;
        }

        $availablePlayers = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId);
        $availableIds = $availablePlayers->pluck('id')->toArray();

        foreach ($playerIds as $playerId) {
            if (!in_array($playerId, $availableIds)) {
                $errors[] = 'One or more selected players are not available.';
                break;
            }
        }

        // Validate position requirements for the formation
        $selectedPlayers = $availablePlayers->filter(fn ($p) => in_array($p->id, $playerIds));
        $positionCounts = $selectedPlayers->groupBy('position_group')->map->count();

        foreach ($requirements as $positionGroup => $requiredCount) {
            $actualCount = $positionCounts->get($positionGroup, 0);
            if ($actualCount !== $requiredCount) {
                $errors[] = "Formation {$formation->value} requires {$requiredCount} {$positionGroup}(s), but you selected {$actualCount}.";
            }
        }

        return $errors;
    }

    /**
     * Auto-select best XI by overall_score, respecting formation requirements.
     * Returns array of player IDs.
     */
    public function autoSelectLineup(
        string $gameId,
        string $teamId,
        Carbon $matchDate,
        string $competitionId,
        ?Formation $formation = null
    ): array {
        return $this->selectBestXI(
            $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId),
            $formation
        )->pluck('id')->toArray();
    }

    /**
     * Select the best XI from a collection of players, respecting formation requirements.
     * Returns Collection of GamePlayer objects.
     *
     * This is the core selection algorithm used by both autoSelectLineup (for match lineups)
     * and for calculating opponent team ratings.
     */
    public function selectBestXI(Collection $availablePlayers, ?Formation $formation = null): Collection
    {
        $formation = $formation ?? Formation::F_4_4_2;
        $requirements = $formation->requirements();

        $selected = collect();

        // Group players by position category
        $grouped = $availablePlayers->groupBy(fn ($p) => $p->position_group);

        // Select players for each position group
        foreach ($requirements as $positionGroup => $count) {
            $positionPlayers = ($grouped->get($positionGroup) ?? collect())
                ->sortByDesc('overall_score')
                ->take($count);

            $selected = $selected->merge($positionPlayers);
        }

        // If we don't have enough for standard formation, fill with best available
        if ($selected->count() < 11) {
            $selectedIds = $selected->pluck('id')->toArray();
            $remaining = $availablePlayers
                ->filter(fn ($p) => !in_array($p->id, $selectedIds))
                ->sortByDesc('overall_score');

            foreach ($remaining as $player) {
                if ($selected->count() >= 11) {
                    break;
                }
                $selected->push($player);
            }
        }

        return $selected;
    }

    /**
     * Calculate the average overall score for a collection of players.
     */
    public function calculateTeamAverage(Collection $players): int
    {
        if ($players->isEmpty()) {
            return 0;
        }

        return (int) round($players->avg('overall_score'));
    }

    /**
     * Get the best XI and their average rating for a team.
     * Convenience method combining selectBestXI and calculateTeamAverage.
     *
     * @return array{players: Collection, average: int}
     */
    public function getBestXIWithAverage(
        string $gameId,
        string $teamId,
        Carbon $matchDate,
        string $competitionId,
        ?Formation $formation = null
    ): array {
        $available = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId);
        $bestXI = $this->selectBestXI($available, $formation);

        return [
            'players' => $bestXI,
            'average' => $this->calculateTeamAverage($bestXI),
        ];
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
     * Save formation to match record.
     */
    public function saveFormation(GameMatch $match, string $teamId, string $formation): void
    {
        if ($match->home_team_id === $teamId) {
            $match->home_formation = $formation;
        } elseif ($match->away_team_id === $teamId) {
            $match->away_formation = $formation;
        }

        $match->save();
    }

    /**
     * Get the formation for a team from a match.
     */
    public function getFormation(GameMatch $match, string $teamId): ?string
    {
        if ($match->home_team_id === $teamId) {
            return $match->home_formation;
        }

        if ($match->away_team_id === $teamId) {
            return $match->away_formation;
        }

        return null;
    }

    /**
     * Save mentality to match record.
     */
    public function saveMentality(GameMatch $match, string $teamId, string $mentality): void
    {
        if ($match->home_team_id === $teamId) {
            $match->home_mentality = $mentality;
        } elseif ($match->away_team_id === $teamId) {
            $match->away_mentality = $mentality;
        }

        $match->save();
    }

    /**
     * Get the mentality for a team from a match.
     */
    public function getMentality(GameMatch $match, string $teamId): ?string
    {
        if ($match->home_team_id === $teamId) {
            return $match->home_mentality;
        }

        if ($match->away_team_id === $teamId) {
            return $match->away_mentality;
        }

        return null;
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
     * Get the previous match's lineup for a team (filtering out unavailable players).
     *
     * @return array{lineup: array, formation: string|null}
     */
    public function getPreviousLineup(
        string $gameId,
        string $teamId,
        string $currentMatchId,
        Carbon $matchDate,
        string $competitionId
    ): array {
        // Find the most recent played match for this team
        $previousMatch = GameMatch::where('game_id', $gameId)
            ->where('played', true)
            ->where('id', '!=', $currentMatchId)
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->orderByDesc('scheduled_date')
            ->first();

        if (!$previousMatch) {
            return ['lineup' => [], 'formation' => null];
        }

        // Get the lineup and formation from that match
        $previousLineup = $this->getLineup($previousMatch, $teamId) ?? [];
        $previousFormation = $this->getFormation($previousMatch, $teamId);

        if (empty($previousLineup)) {
            return ['lineup' => [], 'formation' => $previousFormation];
        }

        // Filter out players who are no longer available
        $availablePlayers = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId);
        $availableIds = $availablePlayers->pluck('id')->toArray();

        $filteredLineup = array_values(array_filter(
            $previousLineup,
            fn ($playerId) => in_array($playerId, $availableIds)
        ));

        return [
            'lineup' => $filteredLineup,
            'formation' => $previousFormation,
        ];
    }

    /**
     * Ensure all matches have lineups set (auto-select for AI teams).
     * Uses the player's preferred lineup, formation, and mentality for their team.
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id (optional, for N+1 optimization)
     * @param array $suspendedPlayerIds Array of player IDs who are suspended (optional, for N+1 optimization)
     */
    public function ensureLineupsForMatches($matches, Game $game, $allPlayersGrouped = null, array $suspendedPlayerIds = []): void
    {
        $playerFormation = $game->default_formation
            ? Formation::tryFrom($game->default_formation)
            : null;
        $playerPreferredLineup = $game->default_lineup;
        $playerMentality = $game->default_mentality ?? 'balanced';

        foreach ($matches as $match) {
            $matchDate = $match->scheduled_date;
            $competitionId = $match->competition_id;

            $this->ensureTeamLineup(
                $match,
                $game,
                'home',
                $matchDate,
                $competitionId,
                $playerFormation,
                $playerPreferredLineup,
                $playerMentality,
                $allPlayersGrouped,
                $suspendedPlayerIds
            );

            $this->ensureTeamLineup(
                $match,
                $game,
                'away',
                $matchDate,
                $competitionId,
                $playerFormation,
                $playerPreferredLineup,
                $playerMentality,
                $allPlayersGrouped,
                $suspendedPlayerIds
            );
        }
    }

    /**
     * Ensure lineup is set for one team in a match.
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id
     * @param array $suspendedPlayerIds Array of player IDs who are suspended
     */
    private function ensureTeamLineup(
        GameMatch $match,
        Game $game,
        string $side,
        $matchDate,
        string $competitionId,
        ?Formation $playerFormation,
        ?array $playerPreferredLineup,
        string $playerMentality,
        $allPlayersGrouped = null,
        array $suspendedPlayerIds = []
    ): void {
        $lineupField = $side . '_lineup';
        $teamIdField = $side . '_team_id';

        if (!empty($match->$lineupField)) {
            return;
        }

        $teamId = $match->$teamIdField;
        $isPlayerTeam = $teamId === $game->team_id;

        // Use pre-loaded players if available, otherwise load (backward compatibility)
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($teamId, collect());
            // Filter available players using pre-loaded suspension data
            $availablePlayers = $teamPlayers->filter(function ($player) use ($matchDate, $suspendedPlayerIds) {
                // Check if suspended (using pre-loaded IDs)
                if (in_array($player->id, $suspendedPlayerIds)) {
                    return false;
                }
                // Check injury
                if ($player->injury_until && $matchDate && $player->injury_until->gt($matchDate)) {
                    return false;
                }
                return true;
            });
        } else {
            // Fallback to original method (triggers N+1 but maintains backward compatibility)
            $availablePlayers = $this->getAvailablePlayers($game->id, $teamId, $matchDate, $competitionId);
        }

        if ($isPlayerTeam && !empty($playerPreferredLineup)) {
            // Select lineup with preferences using pre-loaded data
            $availableIds = $availablePlayers->pluck('id')->toArray();
            $lineup = $this->selectLineupWithPreferencesFromCollection(
                $availablePlayers,
                $playerFormation,
                $playerPreferredLineup,
                $availableIds
            );
        } else {
            // Auto-select best XI from available players
            $lineup = $this->selectBestXI($availablePlayers, $playerFormation)->pluck('id')->toArray();
        }

        $this->saveLineup($match, $teamId, $lineup);

        if ($isPlayerTeam) {
            if ($playerFormation) {
                $this->saveFormation($match, $teamId, $playerFormation->value);
            }
            $this->saveMentality($match, $teamId, $playerMentality);
        }
    }

    /**
     * Select lineup using preferred players from a pre-loaded collection (no DB queries).
     */
    private function selectLineupWithPreferencesFromCollection(
        Collection $availablePlayers,
        ?Formation $formation,
        array $preferredLineup,
        array $availableIds
    ): array {
        $formation = $formation ?? Formation::F_4_4_2;
        $requirements = $formation->requirements();

        // Separate preferred players into available and unavailable
        $availablePreferred = [];
        $unavailablePositionGroups = [];

        $playersById = $availablePlayers->keyBy('id');

        foreach ($preferredLineup as $playerId) {
            if (in_array($playerId, $availableIds)) {
                $availablePreferred[] = $playerId;
            } else {
                // Find the player to determine their position group for replacement
                $player = $playersById->get($playerId);
                if ($player) {
                    $unavailablePositionGroups[] = $player->position_group;
                }
            }
        }

        // If all preferred players are available, use them
        if (count($availablePreferred) === 11) {
            return $availablePreferred;
        }

        // Start with available preferred players
        $lineup = $availablePreferred;

        // Group remaining available players by position
        $remainingAvailable = $availablePlayers->filter(fn ($p) => !in_array($p->id, $lineup));
        $grouped = $remainingAvailable->groupBy(fn ($p) => $p->position_group);

        // Fill gaps with best available from each missing position group
        foreach ($unavailablePositionGroups as $positionGroup) {
            if (count($lineup) >= 11) {
                break;
            }

            $candidates = ($grouped->get($positionGroup) ?? collect())
                ->filter(fn ($p) => !in_array($p->id, $lineup))
                ->sortByDesc('overall_score');

            $replacement = $candidates->first();
            if ($replacement) {
                $lineup[] = $replacement->id;
            }
        }

        // If still not 11, fill with best available from any position
        if (count($lineup) < 11) {
            $remaining = $availablePlayers
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
}
