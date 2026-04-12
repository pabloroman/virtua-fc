<?php

namespace App\Modules\Lineup\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\PlayerSuspension;

class SubstitutionService
{
    public const MAX_SUBSTITUTIONS = 5;

    public const MAX_WINDOWS = 3;

    public const MAX_ET_SUBSTITUTIONS = 6;

    public const MAX_ET_WINDOWS = 4;

    /**
     * Validate substitution rules only (no processing).
     *
     * @throws \InvalidArgumentException with a raw translation key on validation failure
     */
    public function validateBatchSubstitution(
        GameMatch $match,
        Game $game,
        array $newSubstitutions,
        int $minute,
        array $previousSubstitutions,
        bool $isExtraTime = false,
    ): void {
        // Use higher limits during extra time (6th sub, 4th window)
        $maxSubs = $isExtraTime ? self::MAX_ET_SUBSTITUTIONS : self::MAX_SUBSTITUTIONS;
        $maxWindows = $isExtraTime ? self::MAX_ET_WINDOWS : self::MAX_WINDOWS;

        // Check total substitution limit
        $totalSubs = count($previousSubstitutions) + count($newSubstitutions);
        if ($totalSubs > $maxSubs) {
            throw new \InvalidArgumentException('game.sub_error_limit_reached');
        }

        // Check substitution window limit (half-time at minute 45, pre-extra-time
        // at minute 90, and ET half-time at minute 105 are free windows — they don't
        // count toward the limit)
        $freeMinutes = config('match_simulation.free_sub_window_minutes', [45, 90, 105]);
        $previousMinutes = array_unique(array_column($previousSubstitutions, 'minute'));
        $previousWindows = count(array_filter($previousMinutes, fn ($m) => ! in_array($m, $freeMinutes)));
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
        $suspendedPlayerIds = PlayerSuspension::suspendedPlayerIdsForCompetition($match->competition_id);

        // Pre-load red-carded player IDs up to this minute (single query instead of N)
        $playerOutIds = array_column($newSubstitutions, 'playerOutId');
        $redCardedPlayerIds = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('game_player_id', $playerOutIds)
            ->where('event_type', 'red_card')
            ->where('minute', '<=', $minute)
            ->pluck('game_player_id')
            ->all();

        // Pre-load all player-in candidates for the batch (single query instead of N)
        $playerInIds = array_column($newSubstitutions, 'playerInId');
        $playerInRecords = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereIn('id', $playerInIds)
            ->get()
            ->keyBy('id');

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
            if (in_array($playerOutId, $redCardedPlayerIds)) {
                throw new \InvalidArgumentException('game.sub_error_player_sent_off');
            }

            // Validate player-in belongs to team and exists
            $playerIn = $playerInRecords->get($playerInId);

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

            if ($game->requiresSquadEnrollment() && $playerIn->number === null) {
                throw new \InvalidArgumentException('game.sub_error_player_not_registered');
            }

            if (in_array($playerInId, $batchInIds)) {
                throw new \InvalidArgumentException('game.sub_error_already_on_pitch');
            }

            $batchOutIds[] = $playerOutId;
            $batchInIds[] = $playerInId;
        }
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

        return GamePlayer::with(['player', 'matchState'])->whereIn('id', $lineupIds)->get();
    }

    /**
     * Load both teams' lineups and benches for resimulation.
     *
     * @param  int|null  $minute  If provided, reconstructs the opponent lineup/bench as of
     *                            this minute (only subs with minute ≤ $minute are applied).
     *                            Pass null for a full-match reconstruction (all subs applied).
     * @return array{homePlayers: \Illuminate\Support\Collection, awayPlayers: \Illuminate\Support\Collection, homeBench: \Illuminate\Support\Collection, awayBench: \Illuminate\Support\Collection}
     */
    public function loadTeamsForResimulation(
        GameMatch $match,
        Game $game,
        \Illuminate\Support\Collection $userLineup,
        array $substitutions,
        ?int $minute = null,
    ): array {
        $isUserHome = $match->isHomeTeam($game->team_id);

        // Load opponent full squad (1 query) to derive both lineup and bench
        $opponentTeamId = $isUserHome ? $match->away_team_id : $match->home_team_id;
        $opponentSquad = GamePlayer::with(['player', 'matchState'])
            ->where('game_id', $game->id)
            ->where('team_id', $opponentTeamId)
            ->get();

        $opponentLineupIds = $isUserHome ? ($match->away_lineup ?? []) : ($match->home_lineup ?? []);

        // Read opponent substitution events from match_events (source of truth) rather
        // than $match->substitutions, which is populated once during initial simulation
        // and is not updated by subsequent resimulations. Filtering by minute ensures
        // the reconstructed lineup matches the actual state at $minute — any sub events
        // at minute > $minute will be reverted by doResimulate() anyway.
        $opponentSubEventsQuery = MatchEvent::where('game_match_id', $match->id)
            ->where('team_id', $opponentTeamId)
            ->where('event_type', 'substitution');
        if ($minute !== null) {
            $opponentSubEventsQuery->where('minute', '<=', $minute);
        }
        $opponentSubEvents = $opponentSubEventsQuery->orderBy('minute')->get();

        // Track subbed-out players explicitly so they are excluded from the bench.
        // Without this, a starter who was subbed out ends up back on the bench
        // (because "squad − lineup" sees them as off-pitch), letting the AI wrongly
        // pick them again as a replacement later in the match.
        $opponentSubbedOutIds = [];
        foreach ($opponentSubEvents as $subEvent) {
            $playerOutId = $subEvent->game_player_id;
            $playerInId = $subEvent->metadata['player_in_id'] ?? null;
            if ($playerOutId !== null) {
                $opponentLineupIds = array_values(array_filter(
                    $opponentLineupIds,
                    fn ($id) => $id !== $playerOutId
                ));
                $opponentSubbedOutIds[] = $playerOutId;
            }
            if ($playerInId !== null) {
                $opponentLineupIds[] = $playerInId;
            }
        }

        // Pre-load suspended player IDs for this competition (single query)
        $suspendedPlayerIds = PlayerSuspension::suspendedPlayerIdsForCompetition($match->competition_id);

        $opponentPlayers = $opponentSquad->filter(fn ($p) => in_array($p->id, $opponentLineupIds));
        $opponentBench = $opponentSquad
            ->reject(fn ($p) => in_array($p->id, $opponentLineupIds))
            ->reject(fn ($p) => in_array($p->id, $opponentSubbedOutIds))
            ->reject(fn ($p) => $p->isInjured($match->scheduled_date))
            ->reject(fn ($p) => in_array($p->id, $suspendedPlayerIds))
            ->values();

        // User bench: squad minus active lineup minus subbed-out players minus injured/suspended
        $activeLineupIds = $userLineup->pluck('id')->all();
        $subbedOutIds = array_column($substitutions, 'playerOutId');
        $userSquad = GamePlayer::with(['player', 'matchState'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();
        $userBench = $userSquad
            ->reject(fn ($p) => in_array($p->id, $activeLineupIds))
            ->reject(fn ($p) => in_array($p->id, $subbedOutIds))
            ->reject(fn ($p) => $p->isInjured($match->scheduled_date))
            ->reject(fn ($p) => in_array($p->id, $suspendedPlayerIds))
            ->when($game->requiresSquadEnrollment(), fn ($c) => $c->reject(fn ($p) => $p->number === null))
            ->values();

        return [
            'homePlayers' => $isUserHome ? $userLineup : $opponentPlayers,
            'awayPlayers' => $isUserHome ? $opponentPlayers : $userLineup,
            'homeBench' => $isUserHome ? $userBench : $opponentBench,
            'awayBench' => $isUserHome ? $opponentBench : $userBench,
        ];
    }

}
