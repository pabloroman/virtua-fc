<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;

class ShowLiveMatch
{
    public function __invoke(string $gameId, string $matchId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $playerMatch = GameMatch::with([
            'homeTeam',
            'awayTeam',
            'competition',
            'events.gamePlayer.player',
        ])->where('game_id', $gameId)->findOrFail($matchId);

        // Build the events payload for the Alpine.js component
        $events = $playerMatch->events
            ->filter(fn ($e) => $e->event_type !== 'assist')
            ->map(fn ($e) => [
                'minute' => $e->minute,
                'type' => $e->event_type,
                'playerName' => $e->gamePlayer->player->name ?? '',
                'teamId' => $e->team_id,
                'metadata' => $e->metadata,
            ])
            ->sortBy('minute')
            ->values()
            ->all();

        // Pair assists with their goals
        $assists = $playerMatch->events
            ->filter(fn ($e) => $e->event_type === 'assist')
            ->keyBy('minute');

        $events = array_map(function ($event) use ($assists) {
            if (in_array($event['type'], ['goal', 'own_goal']) && isset($assists[$event['minute']])) {
                $event['assistPlayerName'] = $assists[$event['minute']]->gamePlayer->player->name ?? null;
            }
            return $event;
        }, $events);

        // Load other matches from the same competition/matchday for the ticker
        $otherMatches = GameMatch::with(['homeTeam', 'awayTeam', 'events'])
            ->where('game_id', $gameId)
            ->where('competition_id', $playerMatch->competition_id)
            ->where('round_number', $playerMatch->round_number)
            ->where('id', '!=', $playerMatch->id)
            ->get()
            ->map(fn ($m) => [
                'homeTeam' => $m->homeTeam->name,
                'homeTeamImage' => $m->homeTeam->image,
                'awayTeam' => $m->awayTeam->name,
                'awayTeamImage' => $m->awayTeam->image,
                'homeScore' => $m->home_score,
                'awayScore' => $m->away_score,
                'goalMinutes' => $m->events
                    ->filter(fn ($e) => in_array($e->event_type, ['goal', 'own_goal']))
                    ->map(fn ($e) => [
                        'minute' => $e->minute,
                        'side' => ($e->event_type === 'goal' && $e->team_id === $m->home_team_id)
                            || ($e->event_type === 'own_goal' && $e->team_id === $m->away_team_id)
                            ? 'home' : 'away',
                    ])
                    ->sortBy('minute')
                    ->values()
                    ->all(),
            ])
            ->all();

        // Build the results URL for the "Continue" button
        $resultsUrl = route('game.results', [
            'gameId' => $game->id,
            'competition' => $playerMatch->competition_id,
            'matchday' => $playerMatch->round_number,
        ]);

        return view('live-match', [
            'game' => $game,
            'match' => $playerMatch,
            'events' => $events,
            'otherMatches' => $otherMatches,
            'resultsUrl' => $resultsUrl,
        ]);
    }
}
