<?php

namespace App\Modules\Lineup\Services;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\RotationPolicy;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Support\PositionMapper;
use App\Support\PositionSlotMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LineupService
{
    public function __construct(
        private readonly FormationRecommender $formationRecommender,
        private readonly AITacticsService $aiTactics,
    ) {}

    /**
     * Get available players (not injured/suspended) for a team.
     * Batch loads suspensions to avoid N+1 queries.
     *
     * @param bool $requireEnrollment When true, excludes players without a squad number.
     *                                 Should be true for user's team outside preseason, false otherwise.
     */
    public function getAvailablePlayers(string $gameId, string $teamId, Carbon $matchDate, string $competitionId, bool $requireEnrollment = false): Collection
    {
        $players = GamePlayer::with(['matchState'])
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get();

        // Batch load suspended player IDs for this competition (single query)
        $suspendedPlayerIds = PlayerSuspension::suspendedPlayerIdsForCompetition($gameId, $competitionId);

        // Filter in memory using pre-loaded suspension data
        return $players->filter(function (GamePlayer $player) use ($matchDate, $suspendedPlayerIds, $requireEnrollment) {
            // Enrollment check: unenrolled players can't play in competitive matches
            if ($requireEnrollment && $player->number === null) {
                return false;
            }
            // Check if suspended (using pre-loaded IDs)
            if (in_array($player->id, $suspendedPlayerIds)) {
                return false;
            }
            // Check injury
            if ($player->injury_until && $player->injury_until->gte($matchDate)) {
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
        return GamePlayer::with(['matchState', 'suspensions', 'transferOffers', 'activeLoan'])
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
        return PositionMapper::positionSortOrder($position);
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
        ?array $slotAssignments = null,
        bool $requireEnrollment = false,
    ): array {
        $formation = $formation ?? Formation::F_4_3_3;
        $errors = [];

        if (count($playerIds) !== 11) {
            $errors[] = 'You must select exactly 11 players.';
            return $errors;
        }

        if (count($playerIds) !== count(array_unique($playerIds))) {
            $errors[] = 'Duplicate players detected.';
            return $errors;
        }

        $availablePlayers = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId, $requireEnrollment);
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
     *
     * Applies a rotation policy (defaulting to Balanced) so the fatigue
     * penalty in selectBestXI keeps tired starters from beating fresher
     * subs of the same rating. Without this, the manual "Auto Select"
     * button on the lineup screen would rank purely by raw overall_score
     * and pick burned-out players over rested ones — every automated
     * lineup path already does this, this one was the outlier.
     */
    public function autoSelectLineup(
        string $gameId,
        string $teamId,
        Carbon $matchDate,
        string $competitionId,
        ?Formation $formation = null,
        bool $requireEnrollment = false,
        ?RotationPolicy $rotationPolicy = null,
    ): array {
        return $this->selectBestXI(
            $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId, $requireEnrollment),
            $formation,
            $rotationPolicy ?? RotationPolicy::Balanced,
        )->pluck('id')->toArray();
    }

    /**
     * Select the best XI from a collection of players, respecting formation slot requirements.
     * Returns Collection of GamePlayer objects.
     *
     * Delegates to FormationRecommender which runs a multi-pass algorithm
     * (primary position → secondary → swap → weighted fallback) to find the
     * best natural fit for each slot in the formation. This avoids the old
     * position-group-based selection which could exclude natural-fit players
     * when a group's top-rated players didn't match the specific slots needed.
     */
    public function selectBestXI(Collection $availablePlayers, ?Formation $formation = null, ?RotationPolicy $rotationPolicy = null): Collection
    {
        $formation = $formation ?? Formation::F_4_3_3;

        if ($availablePlayers->count() <= 11) {
            return $availablePlayers;
        }

        // When a rotation policy is active, adjust overall_score before passing
        // to the recommender so it accounts for fatigue in its rating-based ranking.
        $pool = $availablePlayers;
        if ($rotationPolicy !== null) {
            $pool = $availablePlayers->map(function ($player) use ($rotationPolicy) {
                $clone = clone $player;
                $clone->overall_score = (int) round($this->effectiveScore($player, $rotationPolicy));

                return $clone;
            });
        }

        $bestXI = $this->formationRecommender->bestXIFor($formation, $pool);

        $selectedIds = collect($bestXI)
            ->pluck('player.id')
            ->filter()
            ->toArray();

        // Return the original (non-cloned) players to preserve model state
        return $availablePlayers->filter(fn ($p) => in_array($p->id, $selectedIds));
    }

    /**
     * Calculate effective score for AI rotation: penalizes low-fitness players.
     * Players above the policy's threshold are unaffected. Below it, score
     * degrades linearly down to (overall_score × floor) at fitness 0.
     *
     * Example: under Aggressive (threshold 90, floor 0.30) an 85-rated player
     * at fitness 40 → 85 × (0.30 + 40/90 × 0.70) = 85 × 0.611 ≈ 52, which is
     * easily beaten by a fresh same-position sub rated in the 60s.
     */
    private function effectiveScore(GamePlayer $player, RotationPolicy $policy): float
    {
        $threshold = $policy->threshold();

        if ($player->fitness >= $threshold) {
            return (float) $player->overall_score;
        }

        $floor = $policy->floor();
        $fitnessMultiplier = $floor + ($player->fitness / $threshold) * (1.0 - $floor);

        return $player->overall_score * $fitnessMultiplier;
    }

    /**
     * True when a player's fitness is below the policy's rotation threshold —
     * tired enough that a fresher same-position sub should be considered
     * before pinning them as a preferred starter.
     */
    private function isTired(GamePlayer $player, RotationPolicy $policy): bool
    {
        return $player->fitness < $policy->threshold();
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
        ?Formation $formation = null,
        bool $requireEnrollment = false,
    ): array {
        $available = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId, $requireEnrollment);
        $bestXI = $this->selectBestXI($available, $formation);

        return [
            'players' => $bestXI,
            'average' => $this->aiTactics->calculateTeamAverage($bestXI),
        ];
    }

    /**
     * Save a team's lineup state (player list, optionally formation, optionally
     * slot assignments) to a match record.
     *
     * The slot assignments map is the authoritative player-to-slot layout for
     * this match. When the caller passes a formation but no slot map, we
     * compute one on the fly via FormationRecommender so every saved lineup
     * ends up with a complete {slotId: playerId} snapshot.
     *
     * @param  array<int, string>  $playerIds
     * @param  array<int|string, string>|null  $slotAssignments  [slotId => playerId]
     */
    public function saveLineup(
        GameMatch $match,
        string $teamId,
        array $playerIds,
        ?Formation $formation = null,
        ?array $slotAssignments = null,
    ): void {
        $prefix = $this->prefixFor($match, $teamId);
        if ($prefix === null) {
            return;
        }

        $match->{"{$prefix}_lineup"} = $playerIds;

        if ($formation !== null) {
            $match->{"{$prefix}_formation"} = $formation->value;
        }

        // Resolve the formation we should use to compute the slot map: the
        // explicitly-passed one wins, else whatever is already on the match.
        $formationForSlots = $formation ?? Formation::tryFrom($match->{"{$prefix}_formation"} ?? '');

        if ($slotAssignments === null && $formationForSlots !== null && ! empty($playerIds)) {
            $players = $this->loadPlayersForLineup($match->game_id, $teamId, $playerIds);
            if ($players->isNotEmpty()) {
                $slotAssignments = $this->computeSlotAssignments($formationForSlots, $players);
            }
        }

        if ($slotAssignments !== null) {
            $match->{"{$prefix}_slot_assignments"} = $slotAssignments;
        }

        $match->save();
    }

    /**
     * Compute the {slotId => playerId} map for a given formation + squad,
     * honoring any caller-provided manual pins. Thin wrapper over
     * FormationRecommender::bestXIFor that flattens the response to the
     * shape consumed by the frontend and persisted to the DB.
     *
     * @param  array<int|string, string>  $manualAssignments
     * @return array<int|string, string>  [slotId => playerId]
     */
    public function computeSlotAssignments(
        Formation $formation,
        Collection $players,
        array $manualAssignments = [],
    ): array {
        $bestXI = $this->formationRecommender->bestXIFor($formation, $players, $manualAssignments);

        $map = [];
        foreach ($bestXI as $row) {
            if ($row['player'] === null) {
                continue;
            }
            $map[(string) $row['slot']['id']] = $row['player']['id'];
        }

        return $map;
    }

    /**
     * Return the authoritative slot map for a team in a match. If the match
     * row already has a persisted map, use it as-is. Otherwise lazily compute
     * from the stored lineup + formation (no persistence — read paths stay
     * side-effect-free). Falls back to an empty array when there's nothing
     * to compute from.
     *
     * @return array<int|string, string>  [slotId => playerId]
     */
    public function resolveSlotAssignments(GameMatch $match, string $teamId): array
    {
        $prefix = $this->prefixFor($match, $teamId);
        if ($prefix === null) {
            return [];
        }

        $persisted = $match->{"{$prefix}_slot_assignments"} ?? null;
        if (is_array($persisted) && ! empty($persisted)) {
            return $persisted;
        }

        $lineup = $match->{"{$prefix}_lineup"} ?? null;
        $formationValue = $match->{"{$prefix}_formation"} ?? null;
        if (empty($lineup) || empty($formationValue)) {
            return [];
        }

        $formation = Formation::tryFrom($formationValue);
        if ($formation === null) {
            return [];
        }

        $players = $this->loadPlayersForLineup($match->game_id, $teamId, $lineup);
        if ($players->isEmpty()) {
            return [];
        }

        return $this->computeSlotAssignments($formation, $players);
    }

    /**
     * Determine which side of a match a team is on. Returns 'home', 'away',
     * or null if the team isn't playing in this match.
     */
    private function prefixFor(GameMatch $match, string $teamId): ?string
    {
        if ($match->home_team_id === $teamId) {
            return 'home';
        }
        if ($match->away_team_id === $teamId) {
            return 'away';
        }
        return null;
    }

    /**
     * Load a team's GamePlayer records by a specific set of ids. Used by the
     * save/resolve slot-assignment paths — they need full player records
     * (position, secondary_positions, overall_score) to run the algorithm.
     *
     * @param  array<int, string>  $playerIds
     */
    private function loadPlayersForLineup(string $gameId, string $teamId, array $playerIds): Collection
    {
        if (empty($playerIds)) {
            return collect();
        }

        return GamePlayer::with(['matchState'])
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->whereIn('id', $playerIds)
            ->get();
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
     * Also carries over the previous match's slot assignments so the lineup
     * page can render players in the exact spots the user last played them.
     * Without this, an injured player leaves the algorithm free to re-solve
     * placement from scratch, which silently shuffles healthy players into
     * different slots (e.g. a CB injury that cascades into an empty RM).
     * Slot entries pointing at no-longer-available players are dropped so
     * the injured/suspended player's spot simply becomes empty.
     *
     * @return array{lineup: array, slot_assignments: array<int|string, string>}
     */
    public function getPreviousLineup(
        string $gameId,
        string $teamId,
        string $currentMatchId,
        Carbon $matchDate,
        string $competitionId,
        bool $requireEnrollment = false,
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
            return ['lineup' => [], 'slot_assignments' => []];
        }

        // Get the lineup from that match (formation is not carried over —
        // mid-match tactical changes are transient and should not affect defaults)
        $previousLineup = $this->getLineup($previousMatch, $teamId) ?? [];

        if (empty($previousLineup)) {
            return ['lineup' => [], 'slot_assignments' => []];
        }

        // Filter out players who are no longer available
        $availablePlayers = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId, $requireEnrollment);
        $availableIds = $availablePlayers->pluck('id')->toArray();

        $filteredLineup = array_values(array_filter(
            $previousLineup,
            fn ($playerId) => in_array($playerId, $availableIds)
        ));

        $prefix = $this->prefixFor($previousMatch, $teamId);
        $previousSlotMap = $prefix !== null
            ? ($previousMatch->{"{$prefix}_slot_assignments"} ?? [])
            : [];
        $filteredSlotMap = is_array($previousSlotMap)
            ? array_filter($previousSlotMap, fn ($playerId) => in_array($playerId, $availableIds))
            : [];

        return [
            'lineup' => $filteredLineup,
            'slot_assignments' => $filteredSlotMap,
        ];
    }

    /**
     * Ensure all matches have lineups set (auto-select for AI teams).
     * Uses the player's preferred lineup, formation, and mentality for their team.
     * AI teams get squad-fitted formations, reputation-driven mentality, and fitness rotation.
     *
     * @param Collection|null $allPlayersGrouped Pre-loaded players grouped by team_id (optional, for N+1 optimization)
     * @param array $suspendedByCompetition Map of competition_id => [player_ids] who are suspended (optional, for N+1 optimization)
     * @param Collection|null $clubProfiles Pre-loaded ClubProfiles keyed by team_id (optional, for AI mentality)
     */
    public function ensureLineupsForMatches($matches, Game $game, $allPlayersGrouped = null, array $suspendedByCompetition = [], $clubProfiles = null): void
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
        $playerRotationPolicy = $tactics?->default_rotation_policy ?? RotationPolicy::Balanced;

        foreach ($matches as $match) {
            $matchDate = $match->scheduled_date;
            $competitionId = $match->competition_id;
            $suspendedPlayerIds = $suspendedByCompetition[$competitionId] ?? [];

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
                $playerRotationPolicy,
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
                $playerRotationPolicy,
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
        ?RotationPolicy $playerRotationPolicy = null,
    ): void {
        // AI teams always rotate at a fixed Balanced cadence — the user's
        // policy only governs their own team's automated XI selection.
        $aiRotationPolicy = RotationPolicy::Balanced;
        $effectivePlayerPolicy = $playerRotationPolicy ?? RotationPolicy::Balanced;
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
                            || ($player->injury_until && $player->injury_until->gte($matchDate));
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
        $requireEnrollment = $isPlayerTeam && $game->requiresSquadEnrollment();

        // Use pre-loaded players if available, otherwise load (backward compatibility)
        if ($allPlayersGrouped !== null) {
            $teamPlayers = $allPlayersGrouped->get($teamId, collect());
            // Filter available players using pre-loaded suspension data
            $availablePlayers = $teamPlayers->filter(function ($player) use ($matchDate, $suspendedPlayerIds, $requireEnrollment) {
                // Enrollment check: unenrolled players can't play in competitive matches
                if ($requireEnrollment && $player->number === null) {
                    return false;
                }
                // Check if suspended (using pre-loaded IDs)
                if (in_array($player->id, $suspendedPlayerIds)) {
                    return false;
                }
                // Check injury
                if ($player->injury_until && $player->injury_until->gte($matchDate)) {
                    return false;
                }
                return true;
            });
        } else {
            // Fallback to original method (triggers N+1 but maintains backward compatibility)
            $availablePlayers = $this->getAvailablePlayers($game->id, $teamId, $matchDate, $competitionId, $requireEnrollment);
        }

        // When the preference-driven path produces slot assignments alongside
        // the lineup, we persist them directly and skip the post-hoc
        // computeSlotAssignments fallback below — keeps lineup composition
        // and slot mapping in lockstep, which is what the fast-mode bug
        // (GK at LB, CF at CB) hinged on losing.
        $preComputedSlotAssignments = null;

        if ($isPlayerTeam && !empty($playerPreferredLineup)) {
            // Select lineup with preferences using pre-loaded data. Applies
            // the user's rotation policy so tired preferred starters get
            // swapped for fresher same-position subs during automated
            // (e.g. fast-mode) runs.
            $availableIds = $availablePlayers->pluck('id')->toArray();
            $allTeamPlayers = $allPlayersGrouped !== null
                ? $allPlayersGrouped->get($teamId, collect())
                : GamePlayer::with(['matchState'])->where('game_id', $game->id)->where('team_id', $teamId)->get();
            $preferenceResult = $this->selectLineupWithPreferencesFromCollection(
                $availablePlayers,
                $allTeamPlayers,
                $playerFormation,
                $playerPreferredLineup,
                $availableIds,
                $effectivePlayerPolicy,
            );
            $lineup = $preferenceResult['lineup'];
            $preComputedSlotAssignments = $preferenceResult['slot_assignments'];
        } elseif ($isPlayerTeam) {
            // Player team without preferred lineup — auto-select with the
            // user's rotation policy so automated matchday prep doesn't
            // burn out the squad.
            $lineup = $this->selectBestXI($availablePlayers, $playerFormation, $effectivePlayerPolicy)->pluck('id')->toArray();
        } else {
            // AI team: use squad-fitted formation with a fixed Balanced
            // rotation cadence, biased toward the team's curated tactical
            // identity.
            $aiFormation = $this->aiTactics->selectAIFormation($availablePlayers, $game->id, $teamId);
            $aiSelectedXI = $this->selectBestXI($availablePlayers, $aiFormation, $aiRotationPolicy);
            $lineup = $aiSelectedXI->pluck('id')->toArray();
        }

        // Set lineup in memory (save deferred to end)
        if ($match->home_team_id === $teamId) {
            $match->home_lineup = $lineup;
        } else {
            $match->away_lineup = $lineup;
        }

        $prefix = $match->home_team_id === $teamId ? 'home' : 'away';

        // Track which formation is active so we can compute slot assignments below.
        $activeFormation = null;

        if ($isPlayerTeam) {
            // Player's team: use their chosen formation, mentality, and instructions
            if ($playerFormation) {
                $match->{$prefix . '_formation'} = $playerFormation->value;
                $activeFormation = $playerFormation;
            } else {
                $activeFormation = Formation::tryFrom($match->{$prefix . '_formation'} ?? '');
            }
            $match->{$prefix . '_mentality'} = $playerMentality;
            $match->{$prefix . '_playing_style'} = $playerPlayingStyle;
            $match->{$prefix . '_pressing'} = $playerPressing;
            $match->{$prefix . '_defensive_line'} = $playerDefLine;
        } else {
            // AI team: set formation, reputation-driven mentality, and AI instructions
            $aiFormation = $aiFormation ?? $this->aiTactics->selectAIFormation($availablePlayers, $game->id, $teamId);
            $isHome = $prefix === 'home' && ! $match->isNeutralVenue();
            $opponentTeamId = $prefix === 'home' ? $match->away_team_id : $match->home_team_id;

            // Reuse already-selected lineup for team average (avoids redundant selectBestXI)
            $teamAvg = $this->aiTactics->calculateTeamAverage($aiSelectedXI ?? $this->selectBestXI($availablePlayers, $aiFormation));

            $opponentPlayers = $allPlayersGrouped?->get($opponentTeamId, collect()) ?? collect();
            $opponentAvg = $opponentPlayers->isNotEmpty()
                ? $this->aiTactics->calculateTeamAverage($this->selectBestXI($opponentPlayers))
                : 0;

            $clubProfile = $clubProfiles?->get($teamId);
            $reputationLevel = $clubProfile?->reputation_level;
            $aggressionBias = (int) ($clubProfile?->tactical_aggression ?? 0);
            $aiMentality = $this->aiTactics->selectAIMentality($reputationLevel, $isHome, $teamAvg, $opponentAvg, $aggressionBias);
            [$aiStyle, $aiPressing, $aiDefLine] = $this->aiTactics->selectAIInstructions($reputationLevel, $isHome, $teamAvg, $opponentAvg, $aggressionBias);

            $match->{$prefix . '_formation'} = $aiFormation->value;
            $match->{$prefix . '_mentality'} = $aiMentality->value;
            $match->{$prefix . '_playing_style'} = $aiStyle->value;
            $match->{$prefix . '_pressing'} = $aiPressing->value;
            $match->{$prefix . '_defensive_line'} = $aiDefLine->value;
            $activeFormation = $aiFormation;
        }

        // Compute and persist the slot map so the frontend never has to
        // re-derive it. The preference-driven path already produced the map
        // via FormationRecommender pins — use it as-is. Otherwise we filter
        // the team's GamePlayer records down to the chosen 11 in memory and
        // run the recommender once.
        if ($preComputedSlotAssignments !== null) {
            $match->{$prefix . '_slot_assignments'} = $preComputedSlotAssignments;
        } elseif ($activeFormation !== null && ! empty($lineup)) {
            $lineupPlayers = $availablePlayers->filter(fn ($p) => in_array($p->id, $lineup, true))->values();
            if ($lineupPlayers->isNotEmpty()) {
                $slotAssignments = $this->computeSlotAssignments($activeFormation, $lineupPlayers);
                $match->{$prefix . '_slot_assignments'} = $slotAssignments;
            }
        }
    }

    /**
     * Select lineup using preferred players from a pre-loaded collection (no DB queries).
     *
     * Treats the preferred lineup as manual pins for FormationRecommender:
     * each available preferred starter is pinned to an unclaimed slot whose
     * label matches their primary (or, if taken, secondary) position. The
     * recommender then fills the remaining slots via its multi-pass
     * algorithm (primary → secondary → swap → weighted → force), so any
     * slot the preferences can't cover gets a natural-fit substitute
     * rather than the highest-OVR-from-any-position fallback that used to
     * land goalkeepers at fullback during fast-mode runs.
     *
     * When $rotationPolicy is set, each preferred starter is compared
     * head-to-head against the best available compat-100 alternative for
     * their slot using effective score (overall × fatigue multiplier
     * from the policy). If an alternative's effective score beats the
     * preferred starter by more than a small thrash-guard margin, the
     * pin is skipped so Pass 1 can pick the alternative. This handles
     * the congested-fixture case where the whole squad sits below the
     * fatigue threshold — the marginally fresher backup still wins on
     * effective score instead of falling off a binary tired/rested
     * cliff. Score adjustment via effectiveScore further biases the
     * recommender toward fresher players in unpinned slots.
     *
     * @param Collection $availablePlayers Players available for selection (not injured/suspended)
     * @param Collection $allTeamPlayers All team players (including unavailable) for position lookups
     * @return array{lineup: array<int, string>, slot_assignments: array<int|string, string>}
     */
    private function selectLineupWithPreferencesFromCollection(
        Collection $availablePlayers,
        Collection $allTeamPlayers,
        ?Formation $formation,
        array $preferredLineup,
        array $availableIds,
        ?RotationPolicy $rotationPolicy = null,
    ): array {
        $formation = $formation ?? Formation::F_4_3_3;

        $formationSlots = $formation->pitchSlots();
        $slotIdToLabel = [];
        foreach ($formationSlots as $slot) {
            $slotIdToLabel[$slot['id']] = $slot['label'];
        }

        $allPlayersById = $allTeamPlayers->keyBy('id');

        // Build manual pins from the preferred lineup. Injured/suspended
        // players are dropped (no pin); when a rotation policy is active,
        // tired starters that lose a head-to-head effective-score check
        // against the best compat-100 alternative are also dropped so the
        // recommender's Pass 1 can pick the alternative.
        $manualPins = [];
        $usedSlotIds = [];

        foreach ($preferredLineup as $playerId) {
            if (! in_array($playerId, $availableIds, true)) {
                continue;
            }

            $player = $allPlayersById->get($playerId);
            if ($player === null) {
                continue;
            }

            $chosenSlotId = $this->findNaturalSlotForPreferredPlayer($player, $formationSlots, $usedSlotIds);

            if ($chosenSlotId === null) {
                // No natural-fit slot left for this preferred player. Skip
                // the pin; the recommender's later passes may still place
                // them via weighted/force in a slot that genuinely needs
                // filling.
                continue;
            }

            // Head-to-head rotation: when a rotation policy is active and
            // the preferred starter is below the policy's fatigue
            // threshold, compare their effective score against the best
            // compat-100 alternative for the slot. If the alternative
            // wins by more than a thrash-guard margin (1.0 effective
            // pts), skip the pin so Pass 1 picks the alternative. Fresh
            // preferred starters are always pinned — the user chose them
            // and they're not tired enough to rotate. Without a compat-100
            // alternative we keep the preferred starter — a fresh
            // out-of-position fill-in is still worse than a tired
            // specialist.
            if ($rotationPolicy !== null && $this->isTired($player, $rotationPolicy)) {
                $slotLabel = $slotIdToLabel[$chosenSlotId];
                $starterEffective = $this->effectiveScore($player, $rotationPolicy);
                $usedPlayerIds = array_values($manualPins);

                $bestAlternative = $availablePlayers
                    ->filter(function ($candidate) use ($player, $slotLabel, $usedPlayerIds) {
                        if ($candidate->id === $player->id || in_array($candidate->id, $usedPlayerIds, true)) {
                            return false;
                        }
                        return PositionSlotMapper::getPlayerCompatibilityScore(
                            $candidate->position,
                            $candidate->secondary_positions,
                            $slotLabel,
                        ) === 100;
                    })
                    ->map(fn ($candidate) => [
                        'player' => $candidate,
                        'effective' => $this->effectiveScore($candidate, $rotationPolicy),
                    ])
                    ->sortByDesc('effective')
                    ->first();

                if ($bestAlternative !== null && $bestAlternative['effective'] > $starterEffective + 1.0) {
                    continue;
                }
            }

            $manualPins[$chosenSlotId] = $playerId;
            $usedSlotIds[] = $chosenSlotId;
        }

        // Apply fitness-adjusted ratings so the recommender's score-ranked
        // passes weigh fresh players over tired ones in unpinned slots.
        $pool = $availablePlayers;
        if ($rotationPolicy !== null) {
            $pool = $availablePlayers->map(function ($p) use ($rotationPolicy) {
                $clone = clone $p;
                $clone->overall_score = (int) round($this->effectiveScore($p, $rotationPolicy));

                return $clone;
            });
        }

        $slotAssignments = $this->computeSlotAssignments($formation, $pool, $manualPins);

        return [
            'lineup' => array_values($slotAssignments),
            'slot_assignments' => $slotAssignments,
        ];
    }

    /**
     * Find the most natural unclaimed formation slot for a preferred player.
     *
     * Tier 1 — slot whose natural occupant (first compat=100 entry in
     *          SLOT_COMPATIBILITY) IS this player's primary position.
     *          Prevents e.g. a Left Winger getting pinned at LM when an
     *          LW slot is open: both are compat 100 for Left Winger, but
     *          LW's natural occupant is "Left Winger", LM's is "Left Midfield".
     * Tier 2 — any other compat-100 slot for the player's primary (e.g.
     *          a Left Winger at LM when the formation has no LW slot).
     * Tier 3 — compat-100 slot via one of the player's secondary positions.
     *
     * Returns null when no slot at compat 100 is available — the caller
     * then leaves the player unpinned and lets FormationRecommender's
     * weighted/force passes decide.
     *
     * @param  array<array{id: int, label: string, role: string, col: int, row: int}>  $formationSlots
     * @param  array<int>  $usedSlotIds
     */
    private function findNaturalSlotForPreferredPlayer(
        GamePlayer $player,
        array $formationSlots,
        array $usedSlotIds,
    ): ?int {
        $position = $player->position;
        $secondaryPositions = $player->secondary_positions ?? [];

        foreach ($formationSlots as $slot) {
            if (in_array($slot['id'], $usedSlotIds, true)) {
                continue;
            }
            $slotPositions = PositionSlotMapper::SLOT_COMPATIBILITY[$slot['label']] ?? [];
            if (array_key_first($slotPositions) === $position) {
                return $slot['id'];
            }
        }

        foreach ($formationSlots as $slot) {
            if (in_array($slot['id'], $usedSlotIds, true)) {
                continue;
            }
            if (PositionSlotMapper::getCompatibilityScore($position, $slot['label']) === 100) {
                return $slot['id'];
            }
        }

        foreach ($formationSlots as $slot) {
            if (in_array($slot['id'], $usedSlotIds, true)) {
                continue;
            }
            foreach ($secondaryPositions as $secondary) {
                if (PositionSlotMapper::getCompatibilityScore($secondary, $slot['label']) === 100) {
                    return $slot['id'];
                }
            }
        }

        return null;
    }

}
