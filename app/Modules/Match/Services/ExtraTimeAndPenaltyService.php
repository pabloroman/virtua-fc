<?php

namespace App\Modules\Match\Services;

use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Modules\Match\DTOs\ExtraTimeProcessResult;
use App\Modules\Match\DTOs\PenaltyProcessResult;
use App\Modules\Match\DTOs\TacticalConfig;
use Illuminate\Support\Collection;

class ExtraTimeAndPenaltyService
{
    public function __construct(
        private readonly MatchSimulator $matchSimulator,
        private readonly MatchEventRepository $matchEventRepository,
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

        foreach ($match->substitutions ?? [] as $sub) {
            if ($sub['team_id'] === $match->home_team_id) {
                $homeEntryMinutes[$sub['player_in_id']] = $sub['minute'];
            } else {
                $awayEntryMinutes[$sub['player_in_id']] = $sub['minute'];
            }
        }

        $tc = TacticalConfig::fromMatch($match);

        $extraTimeResult = $this->matchSimulator->simulateExtraTime(
            $match->homeTeam,
            $match->awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeEntryMinutes,
            $awayEntryMinutes,
            homeFormation: $tc->homeFormation,
            awayFormation: $tc->awayFormation,
            homeMentality: $tc->homeMentality,
            awayMentality: $tc->awayMentality,
            homePlayingStyle: $tc->homePlayingStyle,
            awayPlayingStyle: $tc->awayPlayingStyle,
            homePressing: $tc->homePressing,
            awayPressing: $tc->awayPressing,
            homeDefLine: $tc->homeDefLine,
            awayDefLine: $tc->awayDefLine,
            neutralVenue: $match->isNeutralVenue(),
        );

        $match->update([
            'is_extra_time' => true,
            'home_score_et' => $extraTimeResult->homeScore,
            'away_score_et' => $extraTimeResult->awayScore,
            'home_possession' => $extraTimeResult->homePossession,
            'away_possession' => $extraTimeResult->awayPossession,
        ]);

        $storedEvents = $this->storeExtraTimeEvents($match, $game, $extraTimeResult->events);

        $needsPenalties = $this->checkNeedsPenalties($match, $extraTimeResult->homeScore, $extraTimeResult->awayScore);

        return new ExtraTimeProcessResult(
            homeScoreET: $extraTimeResult->homeScore,
            awayScoreET: $extraTimeResult->awayScore,
            storedEvents: $storedEvents,
            needsPenalties: $needsPenalties,
            homePossession: $extraTimeResult->homePossession,
            awayPossession: $extraTimeResult->awayPossession,
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
     * Load the players currently on the pitch, accounting for substitutions
     * and red cards.
     *
     * @return array{0: Collection, 1: Collection} [homePlayers, awayPlayers]
     */
    private function loadPlayersByTeam(GameMatch $match): array
    {
        $homeIds = $match->home_lineup ?? [];
        $awayIds = $match->away_lineup ?? [];

        foreach ($match->substitutions ?? [] as $sub) {
            $isHome = $sub['team_id'] === $match->home_team_id;

            if ($isHome) {
                $homeIds = array_values(array_filter($homeIds, fn ($id) => $id !== $sub['player_out_id']));
                $homeIds[] = $sub['player_in_id'];
            } else {
                $awayIds = array_values(array_filter($awayIds, fn ($id) => $id !== $sub['player_out_id']));
                $awayIds[] = $sub['player_in_id'];
            }
        }

        // Exclude red-carded players (from regular time and extra time)
        $redCardedIds = MatchEvent::where('game_match_id', $match->id)
            ->where('event_type', MatchEvent::TYPE_RED_CARD)
            ->pluck('game_player_id')
            ->all();

        $homeIds = array_values(array_filter($homeIds, fn ($id) => ! in_array($id, $redCardedIds)));
        $awayIds = array_values(array_filter($awayIds, fn ($id) => ! in_array($id, $redCardedIds)));

        $allIds = array_merge($homeIds, $awayIds);
        $players = GamePlayer::with(['player', 'matchState'])->whereIn('id', $allIds)->get()->keyBy('id');

        return [
            $players->only($homeIds)->values(),
            $players->only($awayIds)->values(),
        ];
    }

    /**
     * Determine if penalties are needed after extra time,
     * accounting for two-legged aggregate scores.
     */
    public function checkNeedsPenalties(GameMatch $match, int $homeScoreET, int $awayScoreET): bool
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
        $ids = $this->matchEventRepository->bulkInsert($events, $game->id, $match->id);

        if (empty($ids)) {
            return collect();
        }

        return MatchEvent::with('gamePlayer.player')
            ->whereIn('id', $ids)
            ->orderBy('minute')
            ->get();
    }
}
