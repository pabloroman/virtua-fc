<?php

namespace App\Modules\Lineup\Services;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LineupService
{
    public function __construct(
        private readonly FormationRecommender $formationRecommender,
    ) {}

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
            if ($player->injury_until && $player->injury_until->gt($matchDate)) {
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
        return GamePlayer::with(['player', 'suspensions', 'transferOffers', 'activeLoan'])
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
            ->sortBy(fn ($p) => self::positionSortOrder($p->position))
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
    public static function positionSortOrder(string $position): int
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
     * Validate lineup: 11 players, all available.
     * When slot assignments are provided, position group restrictions are relaxed
     * (players can be assigned to any slot regardless of their position group).
     */
    public function validateLineup(
        array $playerIds,
        string $gameId,
        string $teamId,
        Carbon $matchDate,
        string $competitionId,
        ?Formation $formation = null,
        ?array $slotAssignments = null
    ): array {
        $formation = $formation ?? Formation::F_4_4_2;
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
                $errors[] = __('squad.player_not_available');
                break;
            }
        }

        // When slot assignments are provided, skip position group validation
        // (the user has explicitly chosen where each player plays)
        if (!empty($slotAssignments)) {
            // Validate slot assignments reference valid players and slots
            $slots = $formation->pitchSlots();
            $slotIds = array_column($slots, 'id');
            foreach ($slotAssignments as $slotId => $playerId) {
                if (!in_array((int) $slotId, $slotIds, true)) {
                    $errors[] = 'Invalid slot assignment.';
                    break;
                }
                if (!in_array($playerId, $playerIds, true)) {
                    $errors[] = 'Slot assigned to player not in lineup.';
                    break;
                }
            }

            return $errors;
        }

        // Without slot assignments, enforce position requirements for the formation
        $requirements = $formation->requirements();
        $selectedPlayers = $availablePlayers->filter(fn ($p) => in_array($p->id, $playerIds));
        $positionCounts = $selectedPlayers->groupBy('position_group')->map->count();

        foreach ($requirements as $positionGroup => $requiredCount) {
            $actualCount = $positionCounts->get($positionGroup, 0);
            if ($actualCount !== $requiredCount) {
                $positionTranslations = [
                    'Goalkeeper' => __('squad.goalkeepers'),
                    'Defender' => __('squad.defenders'),
                    'Midfielder' => __('squad.midfielders'),
                    'Forward' => __('squad.forwards'),
                ];
                $errors[] = __('squad.formation_position_mismatch', [
                    'formation' => $formation->value,
                    'required' => $requiredCount,
                    'position' => $positionTranslations[$positionGroup] ?? $positionGroup,
                    'actual' => $actualCount,
                ]);
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
    public function selectBestXI(Collection $availablePlayers, ?Formation $formation = null, bool $applyFitnessRotation = false): Collection
    {
        $formation = $formation ?? Formation::F_4_4_2;
        $requirements = $formation->requirements();

        // Sort key: effective score accounts for fitness when rotation is enabled
        $sortKey = $applyFitnessRotation
            ? fn ($p) => $this->effectiveScore($p)
            : fn ($p) => $p->overall_score;

        $selected = collect();

        // Group players by position category
        $grouped = $availablePlayers->groupBy(fn ($p) => $p->position_group);

        // Select players for each position group
        foreach ($requirements as $positionGroup => $count) {
            $positionPlayers = ($grouped->get($positionGroup) ?? collect())
                ->sortByDesc($sortKey)
                ->take($count);

            $selected = $selected->merge($positionPlayers);
        }

        // If we don't have enough for standard formation, fill with best available
        if ($selected->count() < 11) {
            $selectedIds = $selected->pluck('id')->toArray();
            $remaining = $availablePlayers
                ->filter(fn ($p) => !in_array($p->id, $selectedIds))
                ->sortByDesc($sortKey);

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
     * Calculate effective score for AI rotation: penalizes low-fitness players.
     * Players at fitness >= 65 are unaffected. Below that, score degrades linearly.
     * Example: 80-rated player at fitness 50 → effective ~71.5
     */
    private function effectiveScore(GamePlayer $player): float
    {
        $fitnessMultiplier = $player->fitness >= 65 ? 1.0 : 0.85 + $player->fitness * 0.0023;

        return $player->overall_score * $fitnessMultiplier;
    }

    /**
     * Select the best formation for an AI team based on squad composition.
     * Uses FormationRecommender to evaluate all formations and pick the best fit.
     */
    public function selectAIFormation(Collection $availablePlayers): Formation
    {
        if ($availablePlayers->count() < 11) {
            return Formation::F_4_4_2;
        }

        return $this->formationRecommender->getBestFormation($availablePlayers);
    }

    /**
     * Select mentality for an AI team based on reputation, venue, and relative strength.
     * Returns a deterministic mentality — same inputs always produce the same output.
     */
    public function selectAIMentality(?string $reputationLevel, bool $isHome, float $teamAvg, float $opponentAvg): Mentality
    {
        if ($reputationLevel === null || $opponentAvg <= 0) {
            return Mentality::BALANCED;
        }

        $diff = $teamAvg - $opponentAvg;
        $isStronger = $diff >= 5;
        $isWeaker = $diff <= -5;

        // Group reputations into tactical tiers
        $tier = match ($reputationLevel) {
            'elite', 'contenders' => 'bold',
            'continental', 'established' => 'mid',
            default => 'cautious', // modest, professional, local
        };

        if ($isHome) {
            if ($isStronger) {
                return $tier === 'cautious' ? Mentality::BALANCED : Mentality::ATTACKING;
            }
            if ($isWeaker) {
                return $tier === 'bold' ? Mentality::BALANCED : Mentality::DEFENSIVE;
            }
            // Similar strength at home
            return Mentality::BALANCED;
        }

        // Away
        if ($isStronger) {
            return $tier === 'cautious' ? Mentality::DEFENSIVE : Mentality::BALANCED;
        }
        if ($isWeaker) {
            return Mentality::DEFENSIVE;
        }
        // Similar strength away
        return $tier === 'bold' ? Mentality::BALANCED : Mentality::DEFENSIVE;
    }

    /**
     * Select tactical instructions for an AI team based on context.
     *
     * @return array{PlayingStyle, PressingIntensity, DefensiveLineHeight}
     */
    public function selectAIInstructions(?string $reputationLevel, bool $isHome, float $teamAvg, float $opponentAvg): array
    {
        $diff = $teamAvg - $opponentAvg;
        $isStronger = $diff >= 5;
        $isWeaker = $diff <= -5;

        $tier = match ($reputationLevel) {
            'elite', 'contenders' => 'bold',
            'continental', 'established' => 'mid',
            default => 'cautious',
        };

        // Playing Style
        if ($isStronger && $isHome) {
            $style = $tier === 'cautious' ? PlayingStyle::BALANCED : PlayingStyle::POSSESSION;
        } elseif ($isWeaker && ! $isHome) {
            $style = $tier === 'bold' ? PlayingStyle::COUNTER_ATTACK : PlayingStyle::COUNTER_ATTACK;
        } elseif ($isWeaker) {
            $style = PlayingStyle::COUNTER_ATTACK;
        } else {
            $style = $tier === 'bold' ? PlayingStyle::POSSESSION : PlayingStyle::BALANCED;
        }

        // Pressing Intensity
        if ($isStronger && $tier === 'bold') {
            $pressing = PressingIntensity::HIGH_PRESS;
        } elseif ($isWeaker && ! $isHome) {
            $pressing = PressingIntensity::LOW_BLOCK;
        } elseif ($isWeaker) {
            $pressing = $tier === 'bold' ? PressingIntensity::STANDARD : PressingIntensity::LOW_BLOCK;
        } else {
            $pressing = PressingIntensity::STANDARD;
        }

        // Defensive Line
        if ($isStronger && $tier === 'bold') {
            $defLine = $isHome ? DefensiveLineHeight::HIGH_LINE : DefensiveLineHeight::NORMAL;
        } elseif ($isWeaker) {
            $defLine = DefensiveLineHeight::DEEP;
        } else {
            $defLine = DefensiveLineHeight::NORMAL;
        }

        return [$style, $pressing, $defLine];
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
     * AI teams get squad-fitted formations, reputation-driven mentality, and fitness rotation.
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id (optional, for N+1 optimization)
     * @param array $suspendedPlayerIds Array of player IDs who are suspended (optional, for N+1 optimization)
     * @param Collection|null $clubProfiles Pre-loaded ClubProfiles keyed by team_id (optional, for AI mentality)
     */
    public function ensureLineupsForMatches($matches, Game $game, $allPlayersGrouped = null, array $suspendedPlayerIds = [], $clubProfiles = null): void
    {
        $tactics = $game->tactics;
        $playerFormation = $tactics?->default_formation
            ? Formation::tryFrom($tactics->default_formation)
            : null;
        $playerPreferredLineup = $tactics?->default_lineup;
        $playerMentality = $tactics?->default_mentality ?? 'balanced';
        $playerPlayingStyle = $tactics?->default_playing_style ?? 'balanced';
        $playerPressing = $tactics?->default_pressing ?? 'standard';
        $playerDefLine = $tactics?->default_defensive_line ?? 'normal';

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
                $suspendedPlayerIds,
                $clubProfiles,
                $playerPlayingStyle,
                $playerPressing,
                $playerDefLine,
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
                $suspendedPlayerIds,
                $clubProfiles,
                $playerPlayingStyle,
                $playerPressing,
                $playerDefLine,
            );

            // Save once per match (covers lineup, formation, mentality for both sides)
            if ($match->isDirty()) {
                $match->save();
            }
        }
    }

    /**
     * Ensure lineup is set for one team in a match.
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id
     * @param array $suspendedPlayerIds Array of player IDs who are suspended
     * @param Collection|null $clubProfiles Pre-loaded ClubProfiles keyed by team_id
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
        array $suspendedPlayerIds = [],
        $clubProfiles = null,
        string $playerPlayingStyle = 'balanced',
        string $playerPressing = 'standard',
        string $playerDefLine = 'normal',
    ): void {
        $lineupField = $side . '_lineup';
        $teamIdField = $side . '_team_id';
        $teamId = $match->$teamIdField;

        // Re-validate existing lineups: if any player is injured/suspended,
        // the user didn't actively set this lineup — regenerate from scratch.
        if (!empty($match->$lineupField)) {
            $existingLineup = $match->$lineupField;

            if ($allPlayersGrouped !== null) {
                $teamPlayers = $allPlayersGrouped->get($teamId, collect());
                $hasUnavailable = $teamPlayers
                    ->filter(fn ($p) => in_array($p->id, $existingLineup))
                    ->contains(function ($player) use ($matchDate, $suspendedPlayerIds) {
                        return in_array($player->id, $suspendedPlayerIds)
                            || ($player->injury_until && $player->injury_until->gt($matchDate));
                    });
            } else {
                $availableIds = $this->getAvailablePlayers($game->id, $teamId, $matchDate, $competitionId)
                    ->pluck('id')->toArray();
                $hasUnavailable = collect($existingLineup)->contains(fn ($id) => !in_array($id, $availableIds));
            }

            if (!$hasUnavailable) {
                return; // All players still available, no changes needed
            }

            // Clear the lineup so it gets regenerated below
            if ($match->home_team_id === $teamId) {
                $match->home_lineup = null;
            } else {
                $match->away_lineup = null;
            }
        }

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
                if ($player->injury_until && $player->injury_until->gt($matchDate)) {
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
            $allTeamPlayers = $allPlayersGrouped !== null
                ? $allPlayersGrouped->get($teamId, collect())
                : GamePlayer::with('player')->where('game_id', $game->id)->where('team_id', $teamId)->get();
            $lineup = $this->selectLineupWithPreferencesFromCollection(
                $availablePlayers,
                $allTeamPlayers,
                $playerFormation,
                $playerPreferredLineup,
                $availableIds
            );
        } elseif ($isPlayerTeam) {
            // Player team without preferred lineup — auto-select without fitness rotation
            $lineup = $this->selectBestXI($availablePlayers, $playerFormation)->pluck('id')->toArray();
        } else {
            // AI team: use squad-fitted formation with fitness rotation
            $aiFormation = $this->selectAIFormation($availablePlayers);
            $aiSelectedXI = $this->selectBestXI($availablePlayers, $aiFormation, applyFitnessRotation: true);
            $lineup = $aiSelectedXI->pluck('id')->toArray();
        }

        // Set lineup in memory (save deferred to end)
        if ($match->home_team_id === $teamId) {
            $match->home_lineup = $lineup;
        } else {
            $match->away_lineup = $lineup;
        }

        $prefix = $match->home_team_id === $teamId ? 'home' : 'away';

        if ($isPlayerTeam) {
            // Player's team: use their chosen formation, mentality, and instructions
            if ($playerFormation) {
                $match->{$prefix . '_formation'} = $playerFormation->value;
            }
            $match->{$prefix . '_mentality'} = $playerMentality;
            $match->{$prefix . '_playing_style'} = $playerPlayingStyle;
            $match->{$prefix . '_pressing'} = $playerPressing;
            $match->{$prefix . '_defensive_line'} = $playerDefLine;
        } else {
            // AI team: set formation, reputation-driven mentality, and AI instructions
            $aiFormation = $aiFormation ?? $this->selectAIFormation($availablePlayers);
            $isHome = $prefix === 'home';
            $opponentTeamId = $isHome ? $match->away_team_id : $match->home_team_id;

            // Reuse already-selected lineup for team average (avoids redundant selectBestXI)
            $teamAvg = $this->calculateTeamAverage($aiSelectedXI ?? $this->selectBestXI($availablePlayers, $aiFormation));

            $opponentPlayers = $allPlayersGrouped?->get($opponentTeamId, collect()) ?? collect();
            $opponentAvg = $opponentPlayers->isNotEmpty()
                ? $this->calculateTeamAverage($this->selectBestXI($opponentPlayers))
                : 0;

            $reputationLevel = $clubProfiles?->get($teamId)?->reputation_level;
            $aiMentality = $this->selectAIMentality($reputationLevel, $isHome, $teamAvg, $opponentAvg);
            [$aiStyle, $aiPressing, $aiDefLine] = $this->selectAIInstructions($reputationLevel, $isHome, $teamAvg, $opponentAvg);

            $match->{$prefix . '_formation'} = $aiFormation->value;
            $match->{$prefix . '_mentality'} = $aiMentality->value;
            $match->{$prefix . '_playing_style'} = $aiStyle->value;
            $match->{$prefix . '_pressing'} = $aiPressing->value;
            $match->{$prefix . '_defensive_line'} = $aiDefLine->value;
        }
    }

    /**
     * Select lineup using preferred players from a pre-loaded collection (no DB queries).
     *
     * @param Collection $availablePlayers Players available for selection (not injured/suspended)
     * @param Collection $allTeamPlayers All team players (including unavailable) for position lookups
     */
    private function selectLineupWithPreferencesFromCollection(
        Collection $availablePlayers,
        Collection $allTeamPlayers,
        ?Formation $formation,
        array $preferredLineup,
        array $availableIds
    ): array {
        $formation = $formation ?? Formation::F_4_4_2;
        $requirements = $formation->requirements();

        // Separate preferred players into available and unavailable
        $availablePreferred = [];
        $unavailablePositionGroups = [];

        // Use all team players for lookups so we can find position groups of unavailable players
        $allPlayersById = $allTeamPlayers->keyBy('id');

        foreach ($preferredLineup as $playerId) {
            if (in_array($playerId, $availableIds)) {
                $availablePreferred[] = $playerId;
            } else {
                // Find the player to determine their position group for replacement
                $player = $allPlayersById->get($playerId);
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
