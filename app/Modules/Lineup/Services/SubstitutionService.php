<?php

namespace App\Modules\Lineup\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;
use Illuminate\Support\Str;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use App\Modules\Match\Services\MatchResimulationService;

class SubstitutionService
{
    public const MAX_SUBSTITUTIONS = 5;

    public const MAX_WINDOWS = 3;

    public const MAX_ET_SUBSTITUTIONS = 6;

    public const MAX_ET_WINDOWS = 4;

    public function __construct(
        private readonly MatchResimulationService $resimulationService,
        private readonly ExtraTimeAndPenaltyService $extraTimeService,
    ) {}

    /**
     * Validate substitution rules and delegate to processBatchSubstitution on success.
     *
     * @throws \InvalidArgumentException with a raw translation key on validation failure
     */
    public function validateAndProcessBatchSubstitution(
        GameMatch $match,
        Game $game,
        array $newSubstitutions,
        int $minute,
        array $previousSubstitutions,
        bool $isExtraTime = false,
    ): array {
        // Use higher limits during extra time (6th sub, 4th window)
        $maxSubs = $isExtraTime ? self::MAX_ET_SUBSTITUTIONS : self::MAX_SUBSTITUTIONS;
        $maxWindows = $isExtraTime ? self::MAX_ET_WINDOWS : self::MAX_WINDOWS;

        // Check total substitution limit
        $totalSubs = count($previousSubstitutions) + count($newSubstitutions);
        if ($totalSubs > $maxSubs) {
            throw new \InvalidArgumentException('game.sub_error_limit_reached');
        }

        // Check substitution window limit
        $previousWindows = count(array_unique(array_column($previousSubstitutions, 'minute')));
        if ($previousWindows >= $maxWindows) {
            throw new \InvalidArgumentException('game.sub_error_windows_reached');
        }

        // Build active lineup from starting lineup + previous subs
        $isHome = $match->isHomeTeam($game->team_id);
        $activeLineupIds = $isHome ? ($match->home_lineup ?? []) : ($match->away_lineup ?? []);

        foreach ($previousSubstitutions as $sub) {
            $activeLineupIds = array_values(array_filter(
                $activeLineupIds,
                fn ($id) => $id !== $sub['playerOutId']
            ));
            $activeLineupIds[] = $sub['playerInId'];
        }

        // Pre-load all suspended player IDs for this competition (single query)
        $suspendedPlayerIds = PlayerSuspension::where('competition_id', $match->competition_id)
            ->where('matches_remaining', '>', 0)
            ->pluck('game_player_id')
            ->all();

        // Validate each sub in the batch
        $batchOutIds = [];
        $batchInIds = [];

        foreach ($newSubstitutions as $sub) {
            $playerOutId = $sub['playerOutId'];
            $playerInId = $sub['playerInId'];

            // Build effective lineup considering earlier subs in this batch
            $effectiveLineup = $activeLineupIds;
            foreach ($batchOutIds as $i => $outId) {
                $effectiveLineup = array_values(array_filter($effectiveLineup, fn ($id) => $id !== $outId));
                $effectiveLineup[] = $batchInIds[$i];
            }

            if (! in_array($playerOutId, $effectiveLineup)) {
                throw new \InvalidArgumentException('game.sub_error_player_not_on_pitch');
            }

            // Prevent substituting a red-carded player
            $wasRedCarded = MatchEvent::where('game_match_id', $match->id)
                ->where('game_player_id', $playerOutId)
                ->where('event_type', 'red_card')
                ->where('minute', '<=', $minute)
                ->exists();

            if ($wasRedCarded) {
                throw new \InvalidArgumentException('game.sub_error_player_sent_off');
            }

            // Validate player-in belongs to team and exists
            $playerIn = GamePlayer::where('id', $playerInId)
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->first();

            if (! $playerIn) {
                throw new \InvalidArgumentException('game.sub_error_invalid_player');
            }

            if (in_array($playerInId, $effectiveLineup)) {
                throw new \InvalidArgumentException('game.sub_error_already_on_pitch');
            }

            if (in_array($playerInId, $suspendedPlayerIds)) {
                throw new \InvalidArgumentException('game.sub_error_player_suspended');
            }

            if ($playerIn->isInjured($match->scheduled_date)) {
                throw new \InvalidArgumentException('game.sub_error_player_injured');
            }

            if (in_array($playerInId, $batchInIds)) {
                throw new \InvalidArgumentException('game.sub_error_already_on_pitch');
            }

            $batchOutIds[] = $playerOutId;
            $batchInIds[] = $playerInId;
        }

        return $this->processBatchSubstitution($match, $game, $newSubstitutions, $minute, $previousSubstitutions, $isExtraTime);
    }

    /**
     * Process a batch of substitutions: revert future events, re-simulate remainder, apply new result.
     * All subs in the batch happen at the same minute (one "window").
     *
     * @param  array  $newSubstitutions  Subs to make now [{playerOutId, playerInId}]
     * @param  array  $previousSubstitutions  Previous subs already made this match [{playerOutId, playerInId, minute}]
     * @param  bool  $isExtraTime  Whether this substitution happens during extra time
     * @return array  Response payload for the frontend
     */
    public function processBatchSubstitution(
        GameMatch $match,
        Game $game,
        array $newSubstitutions,
        int $minute,
        array $previousSubstitutions,
        bool $isExtraTime = false,
    ): array {
        $isUserHome = $match->isHomeTeam($game->team_id);

        // Build the active lineup for both teams (applying ALL subs including the new batch)
        $allSubs = array_merge(
            $previousSubstitutions,
            array_map(fn ($s) => [
                'playerOutId' => $s['playerOutId'],
                'playerInId' => $s['playerInId'],
                'minute' => $minute,
            ], $newSubstitutions),
        );

        $userLineup = $this->buildActiveLineup($match, $game->team_id, $allSubs);
        $opponentLineupIds = $isUserHome ? ($match->away_lineup ?? []) : ($match->home_lineup ?? []);
        $opponentPlayers = GamePlayer::with('player')
            ->whereIn('id', $opponentLineupIds)
            ->get();

        $homePlayers = $isUserHome ? $userLineup : $opponentPlayers;
        $awayPlayers = $isUserHome ? $opponentPlayers : $userLineup;

        // Delegate re-simulation to shared service (pass all subs for energy calculation)
        if ($isExtraTime) {
            $result = $this->resimulationService->resimulateExtraTime($match, $game, $minute, $homePlayers, $awayPlayers, $allSubs);
        } else {
            $result = $this->resimulationService->resimulate($match, $game, $minute, $homePlayers, $awayPlayers, $allSubs);
        }

        // Increment appearances and record each sub in the batch
        foreach ($newSubstitutions as $sub) {
            GamePlayer::where('id', $sub['playerInId'])
                ->increment('appearances');
            GamePlayer::where('id', $sub['playerInId'])
                ->increment('season_appearances');

            $this->recordSubstitution($match, $game->team_id, $sub['playerOutId'], $sub['playerInId'], $minute);

            MatchEvent::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'game_match_id' => $match->id,
                'game_player_id' => $sub['playerOutId'],
                'team_id' => $game->team_id,
                'minute' => $minute,
                'event_type' => MatchEvent::TYPE_SUBSTITUTION,
                'metadata' => json_encode(['player_in_id' => $sub['playerInId']]),
            ]);
        }

        // Build the response for the frontend
        return $this->buildBatchResponse($match, $game, $minute, $newSubstitutions, $result->newHomeScore, $result->newAwayScore, $isExtraTime);
    }

    /**
     * Build the active lineup for the user's team considering all substitutions.
     */
    public function buildActiveLineup(GameMatch $match, string $userTeamId, array $allSubstitutions): \Illuminate\Support\Collection
    {
        $isHome = $match->isHomeTeam($userTeamId);
        $lineupIds = $isHome ? ($match->home_lineup ?? []) : ($match->away_lineup ?? []);

        // Apply substitutions: remove player out, add player in
        foreach ($allSubstitutions as $sub) {
            $lineupIds = array_values(array_filter(
                $lineupIds,
                fn ($id) => $id !== $sub['playerOutId']
            ));
            $lineupIds[] = $sub['playerInId'];
        }

        return GamePlayer::with('player')->whereIn('id', $lineupIds)->get();
    }

    /**
     * Record the substitution in the match's substitutions JSON column.
     */
    private function recordSubstitution(GameMatch $match, string $teamId, string $playerOutId, string $playerInId, int $minute): void
    {
        $substitutions = $match->substitutions ?? [];
        $substitutions[] = [
            'team_id' => $teamId,
            'player_out_id' => $playerOutId,
            'player_in_id' => $playerInId,
            'minute' => $minute,
        ];

        $match->update(['substitutions' => $substitutions]);
    }

    /**
     * Build the JSON response for a batch substitution.
     */
    private function buildBatchResponse(GameMatch $match, Game $game, int $minute, array $newSubstitutions, int $newHomeScore, int $newAwayScore, bool $isExtraTime = false): array
    {
        $formattedEvents = $this->resimulationService->buildEventsResponse($match, $minute);

        // Load player names for each substitution
        $playerIds = [];
        foreach ($newSubstitutions as $sub) {
            $playerIds[] = $sub['playerOutId'];
            $playerIds[] = $sub['playerInId'];
        }
        $players = GamePlayer::with('player')->whereIn('id', $playerIds)->get()->keyBy('id');

        $substitutionDetails = array_map(fn ($sub) => [
            'playerOutId' => $sub['playerOutId'],
            'playerInId' => $sub['playerInId'],
            'playerOutName' => $players->get($sub['playerOutId'])?->player->name ?? '',
            'playerInName' => $players->get($sub['playerInId'])?->player->name ?? '',
            'minute' => $minute,
            'teamId' => $game->team_id,
        ], $newSubstitutions);

        $response = [
            'newScore' => [
                'home' => $newHomeScore,
                'away' => $newAwayScore,
            ],
            'newEvents' => $formattedEvents,
            'substitutions' => $substitutionDetails,
        ];

        if ($isExtraTime) {
            $response['isExtraTime'] = true;
            $response['needsPenalties'] = $this->extraTimeService->checkNeedsPenalties(
                $match->fresh(), $newHomeScore, $newAwayScore
            );
        }

        return $response;
    }
}
