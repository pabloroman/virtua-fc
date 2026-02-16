<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use Illuminate\Support\Str;

class SubstitutionService
{
    public function __construct(
        private readonly MatchResimulationService $resimulationService,
    ) {}

    /**
     * Process a batch of substitutions: revert future events, re-simulate remainder, apply new result.
     * All subs in the batch happen at the same minute (one "window").
     *
     * @param  array  $newSubstitutions  Subs to make now [{playerOutId, playerInId}]
     * @param  array  $previousSubstitutions  Previous subs already made this match [{playerOutId, playerInId, minute}]
     * @return array  Response payload for the frontend
     */
    public function processBatchSubstitution(
        GameMatch $match,
        Game $game,
        array $newSubstitutions,
        int $minute,
        array $previousSubstitutions,
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
        $result = $this->resimulationService->resimulate($match, $game, $minute, $homePlayers, $awayPlayers, $allSubs);

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
        return $this->buildBatchResponse($match, $game, $minute, $newSubstitutions, $result->newHomeScore, $result->newAwayScore);
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
    private function buildBatchResponse(GameMatch $match, Game $game, int $minute, array $newSubstitutions, int $newHomeScore, int $newAwayScore): array
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

        return [
            'newScore' => [
                'home' => $newHomeScore,
                'away' => $newAwayScore,
            ],
            'newEvents' => $formattedEvents,
            'substitutions' => $substitutionDetails,
        ];
    }
}
