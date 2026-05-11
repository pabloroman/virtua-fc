<?php

namespace App\Modules\Lineup\Services;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Models\TeamReputation;
use App\Modules\Competition\Services\CalendarService;
use App\Support\PositionMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LineupService
{
    public function __construct(
        private readonly FormationRecommender $formationRecommender,
        private readonly CalendarService $calendarService,
        private readonly FormationBiasResolver $formationBiasResolver,
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
        // (the user has explicitly chosen where each player plays). The slot
        // map must still be structurally complete: every formation slot
        // filled, no duplicates, exactly one player at the GK slot. Without
        // this guard, a malformed POST could persist a lineup with no
        // goalkeeper or with two players assigned to the same slot, which
        // then renders as a broken pitch even after reconciliation.
        if (!empty($slotAssignments)) {
            $slots = $formation->pitchSlots();
            $slotIds = array_column($slots, 'id');
            $slotIdSet = array_flip(array_map(fn ($id) => (string) $id, $slotIds));

            $normalisedSlotMap = [];
            $invalidSlotFound = false;
            foreach ($slotAssignments as $slotId => $playerId) {
                if (!isset($slotIdSet[(string) $slotId])) {
                    $errors[] = 'Invalid slot assignment.';
                    $invalidSlotFound = true;
                    break;
                }
                if (!in_array($playerId, $playerIds, true)) {
                    $errors[] = 'Slot assigned to player not in lineup.';
                    $invalidSlotFound = true;
                    break;
                }
                $normalisedSlotMap[(string) $slotId] = $playerId;
            }

            if ($invalidSlotFound) {
                return $errors;
            }

            // Every formation slot must be filled exactly once.
            if (count($normalisedSlotMap) !== count($slotIds)) {
                $errors[] = __('squad.formation_slot_map_incomplete');
                return $errors;
            }

            // No two slots may share a player.
            if (count($normalisedSlotMap) !== count(array_unique($normalisedSlotMap))) {
                $errors[] = __('squad.formation_slot_map_duplicates');
                return $errors;
            }

            // The GK slot (label === 'GK') must be filled by a Goalkeeper.
            // Allows the user to drag-swap any outfield player anywhere they
            // like, but blocks the malformed states the lineup page can fall
            // into when the saved GK leaves the squad and nothing refills
            // slot 0 (the bug this fix addresses).
            $gkSlotId = null;
            foreach ($slots as $slot) {
                if ($slot['label'] === 'GK') {
                    $gkSlotId = (string) $slot['id'];
                    break;
                }
            }
            if ($gkSlotId !== null) {
                if (!isset($normalisedSlotMap[$gkSlotId])) {
                    $errors[] = __('squad.formation_missing_goalkeeper');
                    return $errors;
                }
                $gkPlayer = $availablePlayers->firstWhere('id', $normalisedSlotMap[$gkSlotId]);
                if ($gkPlayer === null || $gkPlayer->position_group !== 'Goalkeeper') {
                    $errors[] = __('squad.formation_gk_slot_must_be_goalkeeper');
                    return $errors;
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
        ?Formation $formation = null,
        bool $requireEnrollment = false,
    ): array {
        return $this->selectBestXI(
            $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId, $requireEnrollment),
            $formation
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
    public function selectBestXI(Collection $availablePlayers, ?Formation $formation = null, bool $applyFitnessRotation = false): Collection
    {
        $formation = $formation ?? Formation::F_4_3_3;

        if ($availablePlayers->count() <= 11) {
            return $availablePlayers;
        }

        // When fitness rotation is active, adjust overall_score before passing
        // to the recommender so it accounts for fatigue in its rating-based ranking.
        $pool = $availablePlayers;
        if ($applyFitnessRotation) {
            $pool = $availablePlayers->map(function ($player) {
                $clone = clone $player;
                $clone->overall_score = (int) round($this->effectiveScore($player));

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
     * Players above the threshold are unaffected. Below it, score degrades linearly.
     * With threshold 70 and floor 0.60: 85-rated player at fitness 30 → effective ~64.6,
     * which is now beatable by a fresh same-position sub in the high 60s / low 70s.
     */
    private function effectiveScore(GamePlayer $player): float
    {
        $threshold = (int) config('player.condition.ai_rotation_threshold', 80);

        if ($player->fitness >= $threshold) {
            return (float) $player->overall_score;
        }

        // Linear penalty: 1.0 at threshold, 0.60 at fitness 0
        $fitnessMultiplier = 0.60 + ($player->fitness / $threshold) * 0.40;

        return $player->overall_score * $fitnessMultiplier;
    }

    /**
     * True when a player's fitness is below the rotation threshold and they
     * should be swapped out in favour of a rested alternative when possible.
     */
    private function isTired(GamePlayer $player): bool
    {
        $threshold = (int) config('player.condition.ai_rotation_threshold', 80);

        return $player->fitness < $threshold;
    }

    /**
     * Select the best formation for an AI team based on squad composition.
     * Uses FormationRecommender to evaluate all formations and pick the best fit.
     *
     * When $gameId/$teamId are supplied, the recommender is biased toward the
     * team's curated identity (preferred_formation on ClubProfile, with a
     * reputation-tier fallback). Without those, the recommendation is purely
     * mechanical — used for the bare-collection use cases (e.g. one-off
     * squad analysis where no game/team context is available).
     */
    public function selectAIFormation(
        Collection $availablePlayers,
        ?string $gameId = null,
        ?string $teamId = null,
    ): Formation {
        if ($availablePlayers->count() < 11) {
            return Formation::F_4_3_3;
        }

        $bias = ($gameId && $teamId)
            ? $this->formationBiasResolver->resolveForTeam($gameId, $teamId)
            : [];

        return $this->formationRecommender->getBestFormation($availablePlayers, $bias);
    }

    /**
     * Select mentality for an AI team based on reputation, venue, and relative strength.
     *
     * `$aggressionBias` (-2..+2) shifts the deterministic baseline up or down
     * the DEFENSIVE / BALANCED / ATTACKING ladder so curated club identity
     * (Cholo's Atleti at -2, Gasperini's Atalanta at +2) reads through. Same
     * inputs always produce the same output — the function stays deterministic.
     */
    public function selectAIMentality(
        ?string $reputationLevel,
        bool $isHome,
        float $teamAvg,
        float $opponentAvg,
        int $aggressionBias = 0,
    ): Mentality {
        if ($reputationLevel === null || $opponentAvg <= 0) {
            return Mentality::BALANCED;
        }

        $base = $this->baseAIMentality($reputationLevel, $isHome, $teamAvg, $opponentAvg);

        return $this->shiftLadder(
            [Mentality::DEFENSIVE, Mentality::BALANCED, Mentality::ATTACKING],
            $base,
            $aggressionBias,
        );
    }

    /**
     * Deterministic mentality from the venue/strength/tier inputs alone,
     * without identity bias. Kept as a private helper so the public method
     * can apply the bias as a clean ladder shift on top.
     */
    private function baseAIMentality(string $reputationLevel, bool $isHome, float $teamAvg, float $opponentAvg): Mentality
    {
        $diff = $teamAvg - $opponentAvg;
        $isStronger = $diff >= 5;
        $isWeaker = $diff <= -5;

        // Group reputations into tactical tiers
        $tier = match ($reputationLevel) {
            'elite' => 'bold',
            'continental', 'established' => 'mid',
            default => 'cautious', // modest, local
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
     * Shift `$base` along the supplied ordered ladder by `$bias` positions,
     * clamped to the ladder bounds. Used to apply the curated tactical-
     * aggression bias to mentality, pressing, defensive line, and playing
     * style outputs without re-implementing the base decision tree.
     *
     * @template T
     * @param  list<T>  $ladder  Ordered defensive→attacking enum cases.
     * @param  T  $base
     * @return T
     */
    private function shiftLadder(array $ladder, $base, int $bias)
    {
        if ($bias === 0) {
            return $base;
        }
        $i = array_search($base, $ladder, true);
        if ($i === false) {
            return $base;
        }
        $shifted = max(0, min(count($ladder) - 1, $i + $bias));
        return $ladder[$shifted];
    }

    /**
     * Select tactical instructions for an AI team based on context.
     *
     * `$aggressionBias` (-2..+2) ladder-shifts each output toward more
     * possession / higher press / higher line for positive values, and
     * toward counter-attack / low block / deep line for negative values.
     *
     * @return array{PlayingStyle, PressingIntensity, DefensiveLineHeight}
     */
    public function selectAIInstructions(
        ?string $reputationLevel,
        bool $isHome,
        float $teamAvg,
        float $opponentAvg,
        int $aggressionBias = 0,
    ): array {
        $diff = $teamAvg - $opponentAvg;
        $isStronger = $diff >= 5;
        $isWeaker = $diff <= -5;

        $tier = match ($reputationLevel) {
            'elite' => 'bold',
            'continental', 'established' => 'mid',
            default => 'cautious',
        };

        // Playing Style
        if ($isStronger && $isHome) {
            $style = $tier === 'cautious' ? PlayingStyle::BALANCED : PlayingStyle::POSSESSION;
        } elseif ($isWeaker && ! $isHome) {
            $style = PlayingStyle::COUNTER_ATTACK;
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

        $style = $this->shiftLadder(
            [PlayingStyle::COUNTER_ATTACK, PlayingStyle::BALANCED, PlayingStyle::POSSESSION],
            $style,
            $aggressionBias,
        );
        $pressing = $this->shiftLadder(
            [PressingIntensity::LOW_BLOCK, PressingIntensity::STANDARD, PressingIntensity::HIGH_PRESS],
            $pressing,
            $aggressionBias,
        );
        $defLine = $this->shiftLadder(
            [DefensiveLineHeight::DEEP, DefensiveLineHeight::NORMAL, DefensiveLineHeight::HIGH_LINE],
            $defLine,
            $aggressionBias,
        );

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
        ?Formation $formation = null,
        bool $requireEnrollment = false,
    ): array {
        $available = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId, $requireEnrollment);
        $bestXI = $this->selectBestXI($available, $formation);

        return [
            'players' => $bestXI,
            'average' => $this->calculateTeamAverage($bestXI),
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
     * Reconcile a stored lineup / slot map / pitch positions against the
     * current pool of available players, returning a fully-valid triple plus
     * a list of replacements that happened.
     *
     * Read paths (`ShowLineup`, `ShowLiveMatch`) call this so the user never
     * sees a lineup that references sold / retired / long-term injured
     * players. The previous behaviour was a subtractive filter that left
     * gaps in the formation (e.g. 10 players in 11 slots); this method tops
     * up via `selectLineupWithPreferencesFromCollection` and re-runs
     * `computeSlotAssignments` with the surviving slot entries pinned, so
     * user-intentional placements stick wherever the player is still around.
     *
     * Idempotent: a valid input (11 available players, complete slot map for
     * the formation) returns equivalent output with `changed = false` and
     * `replaced = []`.
     *
     * `pitch_positions` are slot-keyed, so they survive a player swap on
     * the same slot. We only drop entries for slot ids that don't exist in
     * `$formation`.
     *
     * @param  array<int, string>|null            $lineup
     * @param  array<int|string, string>|null     $slotMap
     * @param  array<int|string, array{0:int,1:int}>|null $pitchPositions
     * @return array{
     *     lineup: array<int, string>,
     *     slot_assignments: array<string, string>,
     *     pitch_positions: array<string, array{0:int,1:int}>|null,
     *     replaced: list<array{out_id: string, in_id: string|null}>,
     *     changed: bool,
     * }
     */
    public function reconcileLineupState(
        ?array $lineup,
        ?array $slotMap,
        ?array $pitchPositions,
        Collection $availablePlayers,
        Collection $allTeamPlayers,
        Formation $formation,
        bool $applyFitnessRotation = false,
    ): array {
        $lineup = array_values(array_filter((array) ($lineup ?? []), fn ($id) => is_string($id) && $id !== ''));
        $slotMap = (array) ($slotMap ?? []);

        $availableIds = $availablePlayers->pluck('id')->all();
        $availableIdSet = array_flip($availableIds);

        $formationSlots = $formation->pitchSlots();
        $formationSlotIds = array_map(fn ($s) => (string) $s['id'], $formationSlots);
        $formationSlotIdSet = array_flip($formationSlotIds);

        // Pass 1: split the saved slot map into "still valid" pins and
        // "stale" entries we'll need to backfill. Slot ids not in the
        // current formation are dropped silently.
        $survivingPins = [];
        $staleSlotEntries = []; // [slotId => oldPlayerId]
        foreach ($slotMap as $slotId => $playerId) {
            $slotKey = (string) $slotId;
            if (! isset($formationSlotIdSet[$slotKey])) {
                continue;
            }
            if (! is_string($playerId) || $playerId === '') {
                continue;
            }
            if (! isset($availableIdSet[$playerId])) {
                $staleSlotEntries[$slotKey] = $playerId;
                continue;
            }
            $survivingPins[$slotKey] = $playerId;
        }

        // Pass 2: filter the lineup list. Track the players we removed so
        // we can report them in the banner even if they had no slot entry.
        $filteredLineup = [];
        $removedFromLineup = [];
        foreach ($lineup as $playerId) {
            if (isset($availableIdSet[$playerId])) {
                $filteredLineup[] = $playerId;
            } else {
                $removedFromLineup[] = $playerId;
            }
        }
        // Dedupe in case the saved lineup had duplicates.
        $filteredLineup = array_values(array_unique($filteredLineup));

        $pitchPositionsOut = $this->filterPitchPositions($pitchPositions, $formationSlotIdSet);

        // Short-circuit: nothing stale, lineup already complete, slot map
        // already complete. Return as-is — idempotency contract.
        $slotMapAlreadyComplete = count($survivingPins) === count($formationSlotIds);
        $nothingChanged = empty($staleSlotEntries)
            && empty($removedFromLineup)
            && count($filteredLineup) === 11
            && $slotMapAlreadyComplete;

        if ($nothingChanged) {
            // Normalise key types: callers may have int keys after a JSON
            // round-trip; we always return string-keyed maps.
            $normalisedSlotMap = [];
            foreach ($survivingPins as $slotKey => $playerId) {
                $normalisedSlotMap[(string) $slotKey] = $playerId;
            }

            return [
                'lineup' => $filteredLineup,
                'slot_assignments' => $normalisedSlotMap,
                'pitch_positions' => $pitchPositionsOut,
                'replaced' => [],
                'changed' => false,
            ];
        }

        // Top up the lineup to 11 using the same preference-aware selector
        // that runs at match-time, so the rebuild respects position groups
        // (a sold CB is replaced by another CB first, not by whoever's
        // highest-rated on the bench).
        $rebuiltLineup = $this->selectLineupWithPreferencesFromCollection(
            $availablePlayers,
            $allTeamPlayers,
            $formation,
            $filteredLineup,
            $availableIds,
            applyFitnessRotation: $applyFitnessRotation,
        );

        // The selector can return < 11 if the squad is genuinely short.
        // Keep what it returned — the caller can render whatever fits;
        // `computeSlotAssignments` will simply leave the rest unfilled.
        $rebuiltLineup = array_values(array_unique($rebuiltLineup));

        // Re-run FormationRecommender, pinning surviving slot entries so
        // a user's intentional drag-swap placements stick.
        $rebuiltSet = array_flip($rebuiltLineup);
        $manualPins = array_filter(
            $survivingPins,
            fn ($pid) => isset($rebuiltSet[$pid]),
        );

        $lineupPlayers = $availablePlayers
            ->filter(fn ($p) => isset($rebuiltSet[$p->id]))
            ->values();

        $newSlotMap = $this->computeSlotAssignments($formation, $lineupPlayers, $manualPins);

        // Build replacement report for the banner.
        $replaced = [];
        $reportedOutIds = [];
        foreach ($staleSlotEntries as $slotKey => $oldPlayerId) {
            $replaced[] = [
                'out_id' => $oldPlayerId,
                'in_id' => $newSlotMap[$slotKey] ?? null,
            ];
            $reportedOutIds[$oldPlayerId] = true;
        }
        // Lineup-only stale ids (no slot entry) — still surface them so the
        // user knows somebody got dropped, even if we can't name the slot.
        foreach ($removedFromLineup as $oldPlayerId) {
            if (isset($reportedOutIds[$oldPlayerId])) {
                continue;
            }
            $replaced[] = ['out_id' => $oldPlayerId, 'in_id' => null];
            $reportedOutIds[$oldPlayerId] = true;
        }

        return [
            'lineup' => $rebuiltLineup,
            'slot_assignments' => $newSlotMap,
            'pitch_positions' => $pitchPositionsOut,
            'replaced' => $replaced,
            'changed' => true,
        ];
    }

    /**
     * Strip pitch-position overrides that point at slot ids not present in
     * the current formation. Kept as a helper because `reconcileLineupState`
     * uses it both on the short-circuit and rebuild paths.
     *
     * @param  array<int|string, array{0:int,1:int}>|null  $pitchPositions
     * @param  array<string, int>  $formationSlotIdSet
     * @return array<string, array{0:int,1:int}>|null
     */
    private function filterPitchPositions(?array $pitchPositions, array $formationSlotIdSet): ?array
    {
        if ($pitchPositions === null) {
            return null;
        }

        $filtered = [];
        foreach ($pitchPositions as $slotId => $pos) {
            $slotKey = (string) $slotId;
            if (! isset($formationSlotIdSet[$slotKey])) {
                continue;
            }
            $filtered[$slotKey] = $pos;
        }

        return $filtered;
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
     * @return array{lineup: array, formation: string|null}
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
            return ['lineup' => []];
        }

        // Get the lineup from that match (formation is not carried over —
        // mid-match tactical changes are transient and should not affect defaults)
        $previousLineup = $this->getLineup($previousMatch, $teamId) ?? [];

        if (empty($previousLineup)) {
            return ['lineup' => []];
        }

        // Filter out players who are no longer available
        $availablePlayers = $this->getAvailablePlayers($gameId, $teamId, $matchDate, $competitionId, $requireEnrollment);
        $availableIds = $availablePlayers->pluck('id')->toArray();

        $filteredLineup = array_values(array_filter(
            $previousLineup,
            fn ($playerId) => in_array($playerId, $availableIds)
        ));

        return [
            'lineup' => $filteredLineup,
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

        if ($isPlayerTeam && !empty($playerPreferredLineup)) {
            // Select lineup with preferences using pre-loaded data. Applies
            // fitness rotation so tired preferred starters get swapped for
            // rested same-position subs during automated (e.g. fast-mode) runs,
            // matching AI behaviour and preventing squad exhaustion.
            $availableIds = $availablePlayers->pluck('id')->toArray();
            $allTeamPlayers = $allPlayersGrouped !== null
                ? $allPlayersGrouped->get($teamId, collect())
                : GamePlayer::with(['matchState'])->where('game_id', $game->id)->where('team_id', $teamId)->get();
            $lineup = $this->selectLineupWithPreferencesFromCollection(
                $availablePlayers,
                $allTeamPlayers,
                $playerFormation,
                $playerPreferredLineup,
                $availableIds,
                applyFitnessRotation: true,
            );
        } elseif ($isPlayerTeam) {
            // Player team without preferred lineup — auto-select with fitness
            // rotation so automated matchday prep doesn't burn out the squad.
            $lineup = $this->selectBestXI($availablePlayers, $playerFormation, applyFitnessRotation: true)->pluck('id')->toArray();
        } else {
            // AI team: use squad-fitted formation with fitness rotation,
            // biased toward the team's curated tactical identity.
            $aiFormation = $this->selectAIFormation($availablePlayers, $game->id, $teamId);
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
            $aiFormation = $aiFormation ?? $this->selectAIFormation($availablePlayers, $game->id, $teamId);
            $isHome = $prefix === 'home' && ! $match->isNeutralVenue();
            $opponentTeamId = $prefix === 'home' ? $match->away_team_id : $match->home_team_id;

            // Reuse already-selected lineup for team average (avoids redundant selectBestXI)
            $teamAvg = $this->calculateTeamAverage($aiSelectedXI ?? $this->selectBestXI($availablePlayers, $aiFormation));

            $opponentPlayers = $allPlayersGrouped?->get($opponentTeamId, collect()) ?? collect();
            $opponentAvg = $opponentPlayers->isNotEmpty()
                ? $this->calculateTeamAverage($this->selectBestXI($opponentPlayers))
                : 0;

            $clubProfile = $clubProfiles?->get($teamId);
            $reputationLevel = $clubProfile?->reputation_level;
            $aggressionBias = (int) ($clubProfile?->tactical_aggression ?? 0);
            $aiMentality = $this->selectAIMentality($reputationLevel, $isHome, $teamAvg, $opponentAvg, $aggressionBias);
            [$aiStyle, $aiPressing, $aiDefLine] = $this->selectAIInstructions($reputationLevel, $isHome, $teamAvg, $opponentAvg, $aggressionBias);

            $match->{$prefix . '_formation'} = $aiFormation->value;
            $match->{$prefix . '_mentality'} = $aiMentality->value;
            $match->{$prefix . '_playing_style'} = $aiStyle->value;
            $match->{$prefix . '_pressing'} = $aiPressing->value;
            $match->{$prefix . '_defensive_line'} = $aiDefLine->value;
            $activeFormation = $aiFormation;
        }

        // Compute and persist the slot map so the frontend never has to
        // re-derive it. We already have the team's GamePlayer records loaded
        // as $availablePlayers, so we filter down to the chosen 11 in memory
        // instead of hitting the DB again.
        if ($activeFormation !== null && ! empty($lineup)) {
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
     * When $applyFitnessRotation is true, tired preferred starters are treated
     * like unavailable players and swapped for rested same-position subs where
     * possible. If the entire position group is tired, the preferred tired
     * player is kept rather than forcing an out-of-position replacement —
     * a tired specialist outperforms a rested player in the wrong role.
     *
     * @param Collection $availablePlayers Players available for selection (not injured/suspended)
     * @param Collection $allTeamPlayers All team players (including unavailable) for position lookups
     */
    private function selectLineupWithPreferencesFromCollection(
        Collection $availablePlayers,
        Collection $allTeamPlayers,
        ?Formation $formation,
        array $preferredLineup,
        array $availableIds,
        bool $applyFitnessRotation = false,
    ): array {
        $formation = $formation ?? Formation::F_4_3_3;

        // Separate preferred players into available and unavailable
        $availablePreferred = [];
        // Each replacement slot is [positionGroup, fallbackId|null]. The fallback
        // is the preferred player kept in reserve for the tired-but-no-sub case;
        // it is null when the preferred player is injured/suspended (no fallback
        // is viable in that case).
        $replacementSlots = [];

        // Use all team players for lookups so we can find position groups of unavailable players
        $allPlayersById = $allTeamPlayers->keyBy('id');

        foreach ($preferredLineup as $playerId) {
            if (!in_array($playerId, $availableIds)) {
                // Injured/suspended — must be replaced, no fallback available.
                $player = $allPlayersById->get($playerId);
                if ($player) {
                    $replacementSlots[] = [$player->position_group, null];
                }
                continue;
            }

            $player = $allPlayersById->get($playerId);
            if ($applyFitnessRotation && $player && $this->isTired($player)) {
                // Tired — try to find a rested same-group sub, keep as fallback.
                $replacementSlots[] = [$player->position_group, $playerId];
                continue;
            }

            $availablePreferred[] = $playerId;
        }

        // If all preferred players are available and fresh, use them as-is.
        if (count($availablePreferred) === 11) {
            return $availablePreferred;
        }

        // Start with available preferred players
        $lineup = $availablePreferred;

        // Group remaining available players by position
        $remainingAvailable = $availablePlayers->filter(fn ($p) => !in_array($p->id, $lineup));
        $grouped = $remainingAvailable->groupBy(fn ($p) => $p->position_group);

        // Sort helper — when rotating, rank by fitness-adjusted effective score
        // so rested high-raters float to the top of the fallback lists.
        $rankScore = $applyFitnessRotation
            ? fn ($p) => $this->effectiveScore($p)
            : fn ($p) => $p->overall_score;

        // Fill replacement slots: prefer a rested same-position sub; fall back
        // to the tired preferred player if no rested sub exists in that group.
        foreach ($replacementSlots as [$positionGroup, $fallbackId]) {
            if (count($lineup) >= 11) {
                break;
            }

            $groupCandidates = ($grouped->get($positionGroup) ?? collect())
                ->filter(fn ($p) => !in_array($p->id, $lineup));

            if ($applyFitnessRotation) {
                // Prefer rested players in the same position group.
                $rested = $groupCandidates->filter(fn ($p) => !$this->isTired($p))->sortByDesc($rankScore);
                $replacement = $rested->first();

                // No rested same-group sub: keep the tired preferred starter
                // rather than pulling an out-of-position player in.
                if (!$replacement && $fallbackId !== null && !in_array($fallbackId, $lineup)) {
                    $lineup[] = $fallbackId;
                    continue;
                }
            } else {
                $replacement = $groupCandidates->sortByDesc($rankScore)->first();
            }

            if ($replacement) {
                $lineup[] = $replacement->id;
            } elseif ($fallbackId !== null && !in_array($fallbackId, $lineup)) {
                // Last-ditch: preserve the preferred player even without rotation
                // when no same-group sub exists.
                $lineup[] = $fallbackId;
            }
        }

        // If still not 11, fill with best available from any position.
        if (count($lineup) < 11) {
            $remaining = $availablePlayers
                ->filter(fn ($p) => !in_array($p->id, $lineup))
                ->sortByDesc($rankScore);

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
     * Predict opponent tactics for a match (formation, mentality, instructions).
     *
     * Used by both the lineup page and the dashboard next-match card.
     *
     * `bestXISlots` carries the full slot→player mapping produced by
     * FormationRecommender so consumers (e.g. the Scout Opponent pitch) can
     * render players in the formation slot the recommender actually placed
     * them in — including secondary/swap/weighted placements where a
     * player's position_group does not match the slot's role.
     *
     * @return array{teamAverage: int, avgFitness: int, form: array, formation: string, mentality: string, playingStyle: string, pressing: string, defensiveLine: string, bestXIPlayers: Collection, bestXISlots: array<array{slot: array, player: ?GamePlayer}>}
     */
    public function predictOpponentTactics(
        string $gameId,
        string $opponentTeamId,
        Carbon $matchDate,
        string $competitionId,
        bool $opponentIsHome,
        int $userTeamAverage,
    ): array {
        $availablePlayers = $this->getAvailablePlayers($gameId, $opponentTeamId, $matchDate, $competitionId);

        $predictedFormation = $this->selectAIFormation($availablePlayers, $gameId, $opponentTeamId);

        // Run the recommender directly so we keep the slot→player mapping;
        // selectBestXI drops it. We then derive the flat best-XI collection
        // from the slot assignments to keep both views perfectly consistent.
        $slotAssignments = $this->formationRecommender->bestXIFor($predictedFormation, $availablePlayers);
        $playersById = $availablePlayers->keyBy('id');
        $bestXISlots = [];
        $bestXI = collect();
        foreach ($slotAssignments as $assignment) {
            $playerId = $assignment['player']['id'] ?? null;
            $player = $playerId ? $playersById->get($playerId) : null;
            $bestXISlots[] = ['slot' => $assignment['slot'], 'player' => $player];
            if ($player) {
                $bestXI->push($player);
            }
        }

        $teamAverage = $this->calculateTeamAverage($bestXI);
        $avgFitness = (int) round($bestXI->avg('fitness') ?? 0);

        $opponentReputation = TeamReputation::resolveLevel($gameId, $opponentTeamId);
        $aggressionBias = (int) (ClubProfile::where('team_id', $opponentTeamId)->value('tactical_aggression') ?? 0);

        $predictedMentality = $this->selectAIMentality(
            $opponentReputation,
            $opponentIsHome,
            $teamAverage,
            $userTeamAverage,
            $aggressionBias,
        );

        [$predictedStyle, $predictedPressing, $predictedDefLine] = $this->selectAIInstructions(
            $opponentReputation,
            $opponentIsHome,
            $teamAverage,
            $userTeamAverage,
            $aggressionBias,
        );

        $form = $this->calendarService->getTeamForm($gameId, $opponentTeamId);

        return [
            'teamAverage' => $teamAverage,
            'avgFitness' => $avgFitness,
            'form' => $form,
            'formation' => $predictedFormation->value,
            'mentality' => $predictedMentality->value,
            'playingStyle' => $predictedStyle->value,
            'pressing' => $predictedPressing->value,
            'defensiveLine' => $predictedDefLine->value,
            'bestXIPlayers' => $bestXI,
            'bestXISlots' => $bestXISlots,
        ];
    }
}
