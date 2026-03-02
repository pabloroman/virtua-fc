<?php

namespace App\Http\Views;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Support\PositionMapper;

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

        // Prevent viewing/interacting with matches that are not currently in play
        if ($game->pending_finalization_match_id !== $playerMatch->id) {
            return redirect()->route('show-game', $gameId);
        }

        // Determine if this is a knockout match (cup tie) that could go to ET
        $isKnockout = $playerMatch->cup_tie_id !== null;
        $extraTimeData = null;

        // If match already has ET data (page refresh during ET), prepare it
        if ($isKnockout && $playerMatch->is_extra_time) {
            $extraTimeData = $this->buildExtraTimeData($playerMatch);
        }

        // For two-legged ties, pass first leg aggregate info
        $twoLeggedInfo = null;
        if ($isKnockout) {
            $twoLeggedInfo = $this->buildTwoLeggedInfo($playerMatch);
        }

        // Build the events payload for the Alpine.js component
        // When ET is already played, separate regular (<=93) and ET events (>93)
        $allEvents = $playerMatch->events;
        $regularEvents = $allEvents->filter(fn ($e) => $e->minute <= 93);

        $events = $regularEvents
            ->filter(fn ($e) => $e->event_type !== 'assist')
            ->map(fn ($e) => [
                'minute' => $e->minute,
                'type' => $e->event_type,
                'playerName' => $e->gamePlayer->player->name ?? '',
                'teamId' => $e->team_id,
                'gamePlayerId' => $e->game_player_id,
                'metadata' => $e->metadata,
            ])
            ->sortBy('minute')
            ->values()
            ->all();

        // Pair assists with their goals
        $assists = $regularEvents
            ->filter(fn ($e) => $e->event_type === 'assist')
            ->keyBy('minute');

        $events = array_map(function ($event) use ($assists) {
            if (in_array($event['type'], ['goal', 'own_goal']) && isset($assists[$event['minute']])) {
                $event['assistPlayerName'] = $assists[$event['minute']]->gamePlayer->player->name ?? null;
            }
            return $event;
        }, $events);

        // Load other matches from the same competition/matchday for the ticker
        // For Swiss-format competitions, round_number overlaps between league phase
        // and knockout phase, so also filter by round_name to avoid mixing results.
        $otherMatches = GameMatch::with(['homeTeam', 'awayTeam', 'events'])
            ->where('game_id', $gameId)
            ->where('competition_id', $playerMatch->competition_id)
            ->where('round_number', $playerMatch->round_number)
            ->where('id', '!=', $playerMatch->id)
            ->when(
                $playerMatch->round_name,
                fn ($q) => $q->where('round_name', $playerMatch->round_name),
                fn ($q) => $q->whereNull('round_name'),
            )
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
        $resultsUrl = route('game.results', array_filter([
            'gameId' => $game->id,
            'competition' => $playerMatch->competition_id,
            'matchday' => $playerMatch->round_number,
            'round' => $playerMatch->round_name,
        ]));

        // Load bench players for substitutions (user's team only)
        $isUserHome = $playerMatch->isHomeTeam($game->team_id);
        $userLineupIds = $isUserHome
            ? ($playerMatch->home_lineup ?? [])
            : ($playerMatch->away_lineup ?? []);

        // Existing substitutions already made on this match (for page reload scenario)
        $existingSubstitutions = $playerMatch->substitutions ?? [];

        // Build entry minutes map from existing substitutions
        $entryMinutes = collect($existingSubstitutions)
            ->pluck('minute', 'player_in_id')
            ->all();

        // Starting lineup players (for the "sub out" picker)
        $lineupPlayers = GamePlayer::with('player')
            ->whereIn('id', $userLineupIds)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->player->name ?? '',
                'position' => $p->position,
                'positionAbbr' => PositionMapper::toAbbreviation($p->position),
                'positionGroup' => $p->position_group,
                'positionSort' => LineupService::positionSortOrder($p->position),
                'physicalAbility' => $p->physical_ability,
                'technicalAbility' => $p->technical_ability,
                'age' => $p->age,
                'minuteEntered' => $entryMinutes[$p->id] ?? 0,
            ])
            ->sortBy('positionSort')
            ->values()
            ->all();

        // Batch load suspended player IDs for this competition
        $suspendedPlayerIds = PlayerSuspension::where('competition_id', $playerMatch->competition_id)
            ->where('matches_remaining', '>', 0)
            ->pluck('game_player_id')
            ->toArray();

        // Bench players (all squad players NOT in the starting lineup, not suspended, not injured)
        $matchDate = $playerMatch->scheduled_date;
        $benchPlayers = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->whereNotIn('id', $userLineupIds)
            ->whereNotIn('id', $suspendedPlayerIds)
            ->where(function ($q) use ($matchDate) {
                $q->whereNull('injury_until')
                    ->orWhere('injury_until', '<=', $matchDate);
            })
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->player->name ?? '',
                'position' => $p->position,
                'positionAbbr' => PositionMapper::toAbbreviation($p->position),
                'positionGroup' => $p->position_group,
                'positionSort' => LineupService::positionSortOrder($p->position),
                'physicalAbility' => $p->physical_ability,
                'technicalAbility' => $p->technical_ability,
                'age' => $p->age,
                'minuteEntered' => null,
            ])
            ->sortBy('positionSort')
            ->values()
            ->all();

        // User's current tactical setup
        $userFormation = $isUserHome
            ? ($playerMatch->home_formation ?? '4-4-2')
            : ($playerMatch->away_formation ?? '4-4-2');
        $userMentality = $isUserHome
            ? ($playerMatch->home_mentality ?? 'balanced')
            : ($playerMatch->away_mentality ?? 'balanced');

        $availableFormations = array_map(fn ($f) => [
            'value' => $f->value,
            'tooltip' => $f->tooltip(),
        ], Formation::cases());
        $availableMentalities = array_map(fn ($m) => [
            'value' => $m->value,
            'label' => $m->label(),
            'tooltip' => $m->tooltip(),
        ], Mentality::cases());

        // Tournament knockout context for dramatic result display
        $isTournamentKnockout = $game->isTournamentMode() && $playerMatch->cup_tie_id !== null;
        $knockoutRoundNumber = null;
        $knockoutRoundName = null;

        if ($isTournamentKnockout) {
            $cupTie = CupTie::find($playerMatch->cup_tie_id);
            if ($cupTie) {
                $knockoutRoundNumber = $cupTie->round_number;
                $knockoutRoundName = $playerMatch->round_name ? __($playerMatch->round_name) : null;
            }
        }

        // User's current instructions
        $prefix = $isUserHome ? 'home' : 'away';
        $userPlayingStyle = $playerMatch->{"{$prefix}_playing_style"} ?? 'balanced';
        $userPressing = $playerMatch->{"{$prefix}_pressing"} ?? 'standard';
        $userDefLine = $playerMatch->{"{$prefix}_defensive_line"} ?? 'normal';

        $availablePlayingStyles = array_map(fn (PlayingStyle $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'tooltip' => $s->tooltip(),
        ], PlayingStyle::cases());
        $availablePressing = array_map(fn (PressingIntensity $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'tooltip' => $p->tooltip(),
        ], PressingIntensity::cases());
        $availableDefLine = array_map(fn (DefensiveLineHeight $d) => [
            'value' => $d->value,
            'label' => $d->label(),
            'tooltip' => $d->tooltip(),
        ], DefensiveLineHeight::cases());

        return view('live-match', [
            'game' => $game,
            'match' => $playerMatch,
            'events' => $events,
            'otherMatches' => $otherMatches,
            'resultsUrl' => $resultsUrl,
            'lineupPlayers' => $lineupPlayers,
            'benchPlayers' => $benchPlayers,
            'existingSubstitutions' => $existingSubstitutions,
            'substituteUrl' => route('game.match.substitute', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
            'tacticsUrl' => route('game.match.tactics', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
            'extraTimeUrl' => route('game.match.extra-time', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
            'penaltiesUrl' => route('game.match.penalties', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
            'userFormation' => $userFormation,
            'userMentality' => $userMentality,
            'availableFormations' => $availableFormations,
            'availableMentalities' => $availableMentalities,
            'userPlayingStyle' => $userPlayingStyle,
            'userPressing' => $userPressing,
            'userDefLine' => $userDefLine,
            'availablePlayingStyles' => $availablePlayingStyles,
            'availablePressing' => $availablePressing,
            'availableDefLine' => $availableDefLine,
            'isKnockout' => $isKnockout,
            'extraTimeData' => $extraTimeData,
            'twoLeggedInfo' => $twoLeggedInfo,
            'isTournamentKnockout' => $isTournamentKnockout,
            'knockoutRoundNumber' => $knockoutRoundNumber,
            'knockoutRoundName' => $knockoutRoundName,
            'processingStatusUrl' => route('game.setup-status', $game->id),
        ]);
    }

    /**
     * Build ET data for page-refresh scenario (ET already simulated).
     */
    private function buildExtraTimeData(GameMatch $match): array
    {
        $etEvents = $match->events
            ->filter(fn ($e) => $e->minute > 93);

        $formattedEvents = $etEvents
            ->filter(fn ($e) => $e->event_type !== 'assist')
            ->map(fn ($e) => [
                'minute' => $e->minute,
                'type' => $e->event_type,
                'playerName' => $e->gamePlayer->player->name ?? '',
                'teamId' => $e->team_id,
                'gamePlayerId' => $e->game_player_id,
                'metadata' => $e->metadata,
            ])
            ->sortBy('minute')
            ->values()
            ->all();

        $assists = $etEvents
            ->filter(fn ($e) => $e->event_type === 'assist')
            ->keyBy('minute');

        $formattedEvents = array_map(function ($event) use ($assists) {
            if (in_array($event['type'], ['goal', 'own_goal']) && isset($assists[$event['minute']])) {
                $event['assistPlayerName'] = $assists[$event['minute']]->gamePlayer->player->name ?? null;
            }
            return $event;
        }, $formattedEvents);

        $data = [
            'extraTimeEvents' => $formattedEvents,
            'homeScoreET' => $match->home_score_et ?? 0,
            'awayScoreET' => $match->away_score_et ?? 0,
            'penalties' => null,
            'needsPenalties' => false,
        ];

        if ($match->home_score_penalties !== null) {
            $data['penalties'] = [
                'home' => $match->home_score_penalties,
                'away' => $match->away_score_penalties,
            ];
        } else {
            // ET done but penalties not yet — user needs to pick kickers
            $data['needsPenalties'] = true;
        }

        return $data;
    }

    /**
     * Build first-leg info for two-legged ties.
     */
    private function buildTwoLeggedInfo(GameMatch $match): ?array
    {
        $cupTie = CupTie::with('firstLegMatch')->find($match->cup_tie_id);

        if (! $cupTie) {
            return null;
        }

        $roundConfig = $cupTie->getRoundConfig();

        if (! $roundConfig?->twoLegged) {
            return null;
        }

        // Only relevant for second leg
        if ($cupTie->second_leg_match_id !== $match->id) {
            return null;
        }

        $firstLeg = $cupTie->firstLegMatch;

        if (! $firstLeg?->played) {
            return null;
        }

        return [
            'firstLegHomeScore' => $firstLeg->home_score,
            'firstLegAwayScore' => $firstLeg->away_score,
            'tieHomeTeamId' => $cupTie->home_team_id,
            'tieAwayTeamId' => $cupTie->away_team_id,
        ];
    }
}
