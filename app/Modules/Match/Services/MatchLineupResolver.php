<?php

namespace App\Modules\Match\Services;

use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;

/**
 * Resolves which players are on the pitch at the end of a match.
 *
 * Real-football rule: only the players on the field at the final whistle of
 * extra time are eligible to take penalties. Starting XI minus subs out plus
 * subs in, minus red-carded players.
 */
class MatchLineupResolver
{
    /**
     * Return the player IDs on the pitch at the end of the match, keyed by side.
     *
     * @return array{home: list<string>, away: list<string>}
     */
    public function playerIdsOnPitchAtEnd(GameMatch $match): array
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

        $redCardedIds = MatchEvent::where('game_match_id', $match->id)
            ->where('event_type', MatchEvent::TYPE_RED_CARD)
            ->pluck('game_player_id')
            ->all();

        return [
            'home' => array_values(array_filter($homeIds, fn ($id) => ! in_array($id, $redCardedIds))),
            'away' => array_values(array_filter($awayIds, fn ($id) => ! in_array($id, $redCardedIds))),
        ];
    }

    /**
     * Return the players on the pitch at the end of the match as collections per side.
     *
     * Always queries the database for the exact on-pitch players (max ~22).
     * Pre-loaded squads from callers may be incomplete (e.g. miss subbed-in
     * players), so the resolver is the single source of truth.
     *
     * @return array{0: Collection<int, GamePlayer>, 1: Collection<int, GamePlayer>}  [homePlayers, awayPlayers]
     */
    public function playersOnPitchAtEnd(GameMatch $match): array
    {
        $ids = $this->playerIdsOnPitchAtEnd($match);
        $allIds = array_merge($ids['home'], $ids['away']);
        $players = GamePlayer::with(['matchState'])->whereIn('id', $allIds)->get()->keyBy('id');

        return [
            collect($ids['home'])->map(fn ($id) => $players->get($id))->filter()->values(),
            collect($ids['away'])->map(fn ($id) => $players->get($id))->filter()->values(),
        ];
    }
}
