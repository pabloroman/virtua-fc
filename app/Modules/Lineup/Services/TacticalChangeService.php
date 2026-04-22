<?php

namespace App\Modules\Lineup\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;
use App\Models\MatchEvent;
use App\Modules\Lineup\Enums\Formation;
use Illuminate\Support\Str;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use App\Modules\Match\Services\MatchResimulationService;
use App\Modules\Lineup\Services\SubstitutionService;
use App\Modules\Lineup\Services\LineupService;

class TacticalChangeService
{
    public function __construct(
        private readonly MatchResimulationService $resimulationService,
        private readonly SubstitutionService $substitutionService,
        private readonly ExtraTimeAndPenaltyService $extraTimeService,
        private readonly LineupService $lineupService,
    ) {}

    /**
     * Process combined tactical actions (substitutions + tactical changes) in a single re-simulation.
     */
    public function processLiveMatchChanges(
        GameMatch $match,
        Game $game,
        int $minute,
        array $previousSubstitutions,
        array $newSubstitutions = [],
        ?string $formation = null,
        ?string $mentality = null,
        ?string $playingStyle = null,
        ?string $pressing = null,
        ?string $defensiveLine = null,
        bool $isExtraTime = false,
        bool $autoSubUserTeam = false,
        array $manualSlotPins = [],
    ): array {
        $isUserHome = $match->isHomeTeam($game->team_id);
        $prefix = $isUserHome ? 'home' : 'away';

        // Apply tactical (non-slot) changes to the match record. Slot
        // assignments are recomputed below via LineupService against the
        // post-sub active XI — always, regardless of whether the formation
        // changed — so we never leave the old map stale.
        $matchUpdates = [];

        if ($formation !== null) {
            $matchUpdates["{$prefix}_formation"] = $formation;
        }
        if ($mentality !== null) {
            $matchUpdates["{$prefix}_mentality"] = $mentality;
        }
        if ($playingStyle !== null) {
            $matchUpdates["{$prefix}_playing_style"] = $playingStyle;
        }
        if ($pressing !== null) {
            $matchUpdates["{$prefix}_pressing"] = $pressing;
        }
        if ($defensiveLine !== null) {
            $matchUpdates["{$prefix}_defensive_line"] = $defensiveLine;
        }

        if (! empty($matchUpdates)) {
            $match->update($matchUpdates);
        }

        // Build allSubs = previous + new (with minute)
        $allSubs = array_merge(
            $previousSubstitutions,
            array_map(fn ($s) => [
                'playerOutId' => $s['playerOutId'],
                'playerInId' => $s['playerInId'],
                'minute' => $minute,
            ], $newSubstitutions),
        );

        // Build active lineup and load teams for resimulation. Pass $minute so that
        // the opponent's lineup/bench is reconstructed as of the resimulation point —
        // otherwise subbed-out starters end up wrongly available for reselection.
        $userLineup = $this->substitutionService->buildActiveLineup($match, $game->team_id, $allSubs);
        $teams = $this->substitutionService->loadTeamsForResimulation($match, $game, $userLineup, $allSubs, $minute);
        ['homePlayers' => $homePlayers, 'awayPlayers' => $awayPlayers, 'homeBench' => $homeBench, 'awayBench' => $awayBench] = $teams;

        // Capture effective tactical values before re-simulation
        $effectiveFormation = $match->{"{$prefix}_formation"};
        $effectiveMentality = $match->{"{$prefix}_mentality"};

        // Recompute the user-side slot map against the effective formation +
        // post-sub active XI (10 players if a red card has already been
        // served) whenever the XI or the shape could have changed. This is
        // the fix for the silent out-of-position penalty that used to
        // happen when a sub inherited the outgoing player's slot regardless
        // of position. The same authoritative algorithm runs for every
        // XI-shaping action — subs, formation changes, auto-subs, and
        // drag-pinned placements all flow through
        // LineupService::computeSlotAssignments.
        //
        // Pure tactical changes (mentality / pressing / playing style /
        // defensive line) don't reshape the XI, so the persisted map stays
        // valid and we skip the recompute.
        //
        // $manualSlotPins lets the frontend lock specific slots (populated
        // by in-match drag-swaps — the only way the user can express "this
        // player plays here" without going through the formation selector).
        // We filter pins against the active XI so stale ids don't leak into
        // the placement algorithm.
        $newSlotAssignments = null;
        $shapeChanged = ! empty($newSubstitutions)
            || $formation !== null
            || ! empty($manualSlotPins)
            || $autoSubUserTeam;
        $formationEnum = Formation::tryFrom($effectiveFormation ?? '');
        $userPlayers = $isUserHome ? $homePlayers : $awayPlayers;
        if ($shapeChanged && $formationEnum !== null && $userPlayers->isNotEmpty()) {
            $activeIds = array_flip($userPlayers->pluck('id')->all());
            $validPins = array_filter(
                $manualSlotPins,
                fn ($playerId) => isset($activeIds[$playerId]),
            );

            // When the formation isn't changing, the persisted slot map is
            // the user's current intent — prior drag-swaps have already been
            // baked into it. Pin every player still on the pitch to their
            // current slot so a straight substitution only rewrites the
            // slot of the outgoing player. Without this, the recomputation
            // runs from scratch and can silently undo earlier drag-swaps
            // (e.g. a natural-RB sub enters the RB slot but FormationRecommender
            // re-places a versatile RB/CM back at his primary RB, displacing
            // the incoming player into a non-natural slot with a penalty).
            // A formation change should reshape the XI, so we skip this step
            // in that case. Explicit user pins always win.
            if ($formation === null) {
                $currentAssignments = $match->{"{$prefix}_slot_assignments"} ?? [];
                if (is_array($currentAssignments)) {
                    foreach ($currentAssignments as $slotId => $playerId) {
                        if (! isset($activeIds[$playerId])) {
                            continue;
                        }
                        if (isset($validPins[$slotId])) {
                            continue;
                        }
                        $validPins[$slotId] = $playerId;
                    }
                }
            }

            $newSlotAssignments = $this->lineupService->computeSlotAssignments(
                $formationEnum,
                $userPlayers,
                $validPins,
            );
            $match->update(["{$prefix}_slot_assignments" => $newSlotAssignments]);
        }

        // Single re-simulation with all changes applied
        if ($isExtraTime) {
            $result = $this->resimulationService->resimulateExtraTime($match, $game, $minute, $homePlayers, $awayPlayers, $allSubs, $homeBench, $awayBench);
        } else {
            $result = $this->resimulationService->resimulate(
                $match,
                $game,
                $minute,
                $homePlayers,
                $awayPlayers,
                $allSubs,
                $homeBench,
                $awayBench,
                autoSubUserTeam: $autoSubUserTeam,
            );
        }

        // Rebuild substitutions JSON: keep opponent entries, replace user entries.
        // This cleans up stale entries from previous simulations that were reverted.
        // When $autoSubUserTeam is true, the resimulation may have generated fresh
        // user-team substitution events in the remainder (they exist as MatchEvent
        // rows but are not in $allSubs). Re-read the user substitutions from the
        // events table so the JSON stays authoritative.
        $opponentSubs = collect($match->substitutions ?? [])
            ->filter(fn ($s) => ($s['team_id'] ?? null) !== $game->team_id)
            ->values()
            ->all();

        if ($autoSubUserTeam) {
            $userSubs = MatchEvent::where('game_match_id', $match->id)
                ->where('event_type', MatchEvent::TYPE_SUBSTITUTION)
                ->where('team_id', $game->team_id)
                ->orderBy('minute')
                ->get()
                ->map(fn ($e) => [
                    'team_id' => $game->team_id,
                    'player_out_id' => $e->game_player_id,
                    'player_in_id' => $e->metadata['player_in_id'] ?? null,
                    'minute' => $e->minute,
                ])
                ->filter(fn ($s) => $s['player_in_id'] !== null)
                ->values()
                ->all();
        } else {
            $userSubs = array_map(fn ($s) => [
                'team_id' => $game->team_id,
                'player_out_id' => $s['playerOutId'],
                'player_in_id' => $s['playerInId'],
                'minute' => $s['minute'],
            ], $allSubs);
        }

        $match->update(['substitutions' => array_merge($opponentSubs, $userSubs)]);

        // Record new substitution events and appearances
        $substitutionDetails = [];
        if (! empty($newSubstitutions)) {
            $playerIds = [];
            $eventRows = [];

            foreach ($newSubstitutions as $sub) {
                $eventRows[] = [
                    'id' => Str::uuid()->toString(),
                    'game_id' => $game->id,
                    'game_match_id' => $match->id,
                    'game_player_id' => $sub['playerOutId'],
                    'team_id' => $game->team_id,
                    'minute' => $minute,
                    'event_type' => MatchEvent::TYPE_SUBSTITUTION,
                    'metadata' => json_encode(['player_in_id' => $sub['playerInId']]),
                ];

                $playerIds[] = $sub['playerOutId'];
                $playerIds[] = $sub['playerInId'];
            }

            // Batch: increment appearances on the match-state satellite
            // (subbed-in players belong to the user's team, so they always
            // have a satellite row), insert events.
            $playerInIds = array_column($newSubstitutions, 'playerInId');
            GamePlayerMatchState::bulkIncrementAppearances($playerInIds);
            MatchEvent::insert($eventRows);

            // Load player names for response
            $players = GamePlayer::with('player')->whereIn('id', $playerIds)->get()->keyBy('id');
            $substitutionDetails = array_map(fn ($sub) => [
                'playerOutId' => $sub['playerOutId'],
                'playerInId' => $sub['playerInId'],
                'playerOutName' => $players->get($sub['playerOutId'])?->player->name ?? '',
                'playerInName' => $players->get($sub['playerInId'])?->player->name ?? '',
                'minute' => $minute,
                'teamId' => $game->team_id,
            ], $newSubstitutions);
        }

        // Resolve MVP player name and team for the frontend
        $mvpPlayerName = null;
        $mvpPlayerTeamId = null;
        if ($result->mvpPlayerId) {
            $mvpPlayer = GamePlayer::with('player')->find($result->mvpPlayerId);
            if ($mvpPlayer) {
                $mvpPlayerName = $mvpPlayer->player->name ?? null;
                $mvpPlayerTeamId = $mvpPlayer->team_id;
            }
        }

        // Build combined response
        $response = [
            'newScore' => [
                'home' => $result->newHomeScore,
                'away' => $result->newAwayScore,
            ],
            'newEvents' => $this->resimulationService->buildEventsResponse($match, $minute),
            'formation' => $effectiveFormation,
            'mentality' => $effectiveMentality,
            'playingStyle' => $match->{"{$prefix}_playing_style"},
            'pressing' => $match->{"{$prefix}_pressing"},
            'defensiveLine' => $match->{"{$prefix}_defensive_line"},
            'homePossession' => $result->homePossession,
            'awayPossession' => $result->awayPossession,
            'substitutions' => $substitutionDetails,
            'playerPerformances' => $result->performances,
            'mvpPlayerName' => $mvpPlayerName,
            'mvpPlayerTeamId' => $mvpPlayerTeamId,
            // Authoritative slot map after this round-trip — reflects the
            // post-sub, post-formation reshuffle. The frontend promotes it
            // to `startingSlotMap` so subsequent renders don't replay the
            // now-persisted subs on top of a stale map.
            'slot_assignments' => $newSlotAssignments,
        ];

        if ($isExtraTime) {
            $response['isExtraTime'] = true;
            $response['needsPenalties'] = $this->extraTimeService->checkNeedsPenalties(
                $match->fresh(), $result->newHomeScore, $result->newAwayScore
            );
        }

        return $response;
    }
}
