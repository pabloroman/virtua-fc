<?php

namespace App\Modules\Match\Services;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Modules\Match\DTOs\ExtraTimeProcessResult;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\DTOs\PenaltyProcessResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ExtraTimeAndPenaltyService
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
    ) {}

    /**
     * Simulate extra time for a live match, persist scores and events.
     *
     * Expects $match to have homeTeam/awayTeam loaded (or lazy-loadable).
     */
    public function processExtraTime(GameMatch $match, Game $game): ExtraTimeProcessResult
    {
        [$homePlayers, $awayPlayers] = $this->loadPlayersByTeam($match);

        $homeEntryMinutes = [];
        $awayEntryMinutes = [];
        $allPlayers = $homePlayers->merge($awayPlayers);

        foreach ($match->substitutions ?? [] as $sub) {
            $playerIn = $allPlayers->firstWhere('id', $sub['player_in_id']);
            if ($playerIn) {
                if ($playerIn->team_id === $match->home_team_id) {
                    $homeEntryMinutes[$sub['player_in_id']] = $sub['minute'];
                } else {
                    $awayEntryMinutes[$sub['player_in_id']] = $sub['minute'];
                }
            }
        }

        $extraTimeResult = $this->matchSimulator->simulateExtraTime(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeEntryMinutes,
            $awayEntryMinutes,
        );

        $match->update([
            'is_extra_time' => true,
            'home_score_et' => $extraTimeResult->homeScore,
            'away_score_et' => $extraTimeResult->awayScore,
        ]);

        $storedEvents = $this->storeExtraTimeEvents($match, $game, $extraTimeResult->events);

        $needsPenalties = $this->checkNeedsPenalties($match, $extraTimeResult->homeScore, $extraTimeResult->awayScore);

        return new ExtraTimeProcessResult(
            homeScoreET: $extraTimeResult->homeScore,
            awayScoreET: $extraTimeResult->awayScore,
            storedEvents: $storedEvents,
            needsPenalties: $needsPenalties,
        );
    }

    /**
     * Simulate a penalty shootout for a live match, persist scores.
     */
    public function processPenalties(GameMatch $match, Game $game, array $userKickerOrder): PenaltyProcessResult
    {
        [$homePlayers, $awayPlayers] = $this->loadPlayersByTeam($match);

        $isUserHome = $match->home_team_id === $game->team_id;

        $result = $this->matchSimulator->simulatePenaltyShootout(
            $homePlayers,
            $awayPlayers,
            $isUserHome ? $userKickerOrder : null,
            $isUserHome ? null : $userKickerOrder,
        );

        $match->update([
            'home_score_penalties' => $result['homeScore'],
            'away_score_penalties' => $result['awayScore'],
        ]);

        return new PenaltyProcessResult(
            homeScore: $result['homeScore'],
            awayScore: $result['awayScore'],
            kicks: $result['kicks'],
        );
    }

    /**
     * Load lineup players split by team.
     *
     * @return array{0: Collection, 1: Collection} [homePlayers, awayPlayers]
     */
    private function loadPlayersByTeam(GameMatch $match): array
    {
        $allLineupIds = array_merge($match->home_lineup ?? [], $match->away_lineup ?? []);
        $players = GamePlayer::with('player')->whereIn('id', $allLineupIds)->get();

        return [
            $players->filter(fn ($p) => $p->team_id === $match->home_team_id),
            $players->filter(fn ($p) => $p->team_id === $match->away_team_id),
        ];
    }

    /**
     * Determine if penalties are needed after extra time,
     * accounting for two-legged aggregate scores.
     */
    private function checkNeedsPenalties(GameMatch $match, int $homeScoreET, int $awayScoreET): bool
    {
        $totalHome = $match->home_score + $homeScoreET;
        $totalAway = $match->away_score + $awayScoreET;

        if ($match->cup_tie_id) {
            $cupTie = CupTie::with('firstLegMatch')->find($match->cup_tie_id);

            if ($cupTie && $cupTie->second_leg_match_id === $match->id) {
                $firstLeg = $cupTie->firstLegMatch;
                if ($firstLeg?->played) {
                    // Second leg home = tie's away, so swap for aggregate
                    $totalHome = ($firstLeg->home_score ?? 0) + ($match->away_score + $awayScoreET);
                    $totalAway = ($firstLeg->away_score ?? 0) + ($match->home_score + $homeScoreET);
                }
            }
        }

        return $totalHome === $totalAway;
    }

    /**
     * Persist extra time events as MatchEvent records.
     *
     * @return Collection<MatchEvent>
     */
    private function storeExtraTimeEvents(GameMatch $match, Game $game, Collection $events): Collection
    {
        $now = now();

        $rows = $events->map(fn (MatchEventData $e) => [
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'game_match_id' => $match->id,
            'game_player_id' => $e->gamePlayerId,
            'team_id' => $e->teamId,
            'minute' => $e->minute,
            'event_type' => $e->type,
            'metadata' => $e->metadata ? json_encode($e->metadata) : null,
            'created_at' => $now,
        ])->all();

        if (empty($rows)) {
            return collect();
        }

        MatchEvent::insert($rows);

        $ids = array_column($rows, 'id');

        return MatchEvent::with('gamePlayer.player')
            ->whereIn('id', $ids)
            ->orderBy('minute')
            ->get();
    }
}
