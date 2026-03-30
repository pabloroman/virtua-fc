<?php

namespace App\Modules\Lineup\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use App\Modules\Match\Services\MatchResimulationService;
use App\Modules\Lineup\Services\SubstitutionService;

class TacticalChangeService
{
    public function __construct(
        private readonly MatchResimulationService $resimulationService,
        private readonly SubstitutionService $substitutionService,
        private readonly ExtraTimeAndPenaltyService $extraTimeService,
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
        ?array $pitchPositions = null,
    ): array {
        $isUserHome = $match->isHomeTeam($game->team_id);
        $prefix = $isUserHome ? 'home' : 'away';

        // Apply tactical changes to the match record
        $matchUpdates = [];

        if ($formation !== null) {
            $matchUpdates["{$prefix}_formation"] = $formation;

            // When the formation changes, clear stale slot assignments — slot IDs
            // map to different positions per formation.
            if ($formation !== $match->{"{$prefix}_formation"}) {
                $matchUpdates["{$prefix}_pitch_positions"] = $pitchPositions;
                $matchUpdates["{$prefix}_slot_assignments"] = null;
                $pitchPositions = null; // already queued
            }
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

        // Persist pitch positions on the match (if not already queued during formation change)
        if ($pitchPositions !== null) {
            $matchUpdates["{$prefix}_pitch_positions"] = $pitchPositions;
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

        // Build active lineup and load teams for resimulation
        $userLineup = $this->substitutionService->buildActiveLineup($match, $game->team_id, $allSubs);
        $teams = $this->substitutionService->loadTeamsForResimulation($match, $game, $userLineup, $allSubs);
        ['homePlayers' => $homePlayers, 'awayPlayers' => $awayPlayers, 'homeBench' => $homeBench, 'awayBench' => $awayBench] = $teams;

        // Capture effective tactical values before re-simulation
        $effectiveFormation = $match->{"{$prefix}_formation"};
        $effectiveMentality = $match->{"{$prefix}_mentality"};

        // Single re-simulation with all changes applied
        if ($isExtraTime) {
            $result = $this->resimulationService->resimulateExtraTime($match, $game, $minute, $homePlayers, $awayPlayers, $allSubs, $homeBench, $awayBench);
        } else {
            $result = $this->resimulationService->resimulate($match, $game, $minute, $homePlayers, $awayPlayers, $allSubs, $homeBench, $awayBench);
        }

        // Rebuild substitutions JSON: keep opponent entries, replace user entries with allSubs.
        // This cleans up stale entries from previous simulations that were reverted.
        $opponentSubs = collect($match->substitutions ?? [])
            ->filter(fn ($s) => ($s['team_id'] ?? null) !== $game->team_id)
            ->values()
            ->all();

        $userSubs = array_map(fn ($s) => [
            'team_id' => $game->team_id,
            'player_out_id' => $s['playerOutId'],
            'player_in_id' => $s['playerInId'],
            'minute' => $s['minute'],
        ], $allSubs);

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

            // Batch: increment appearances, insert events
            $playerInIds = array_column($newSubstitutions, 'playerInId');
            GamePlayer::whereIn('id', $playerInIds)->update([
                'appearances' => DB::raw('appearances + 1'),
                'season_appearances' => DB::raw('season_appearances + 1'),
            ]);
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
