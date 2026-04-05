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
use App\Modules\Match\Services\MatchResimulationService;
use App\Support\PitchGrid;
use App\Support\PositionMapper;
use App\Support\PositionSlotMapper;
use App\Support\TeamColors;

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
            'mvpPlayer.player',
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

        $events = MatchResimulationService::formatMatchEvents($regularEvents);

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

        // Starting lineup players (for the "sub out" picker)
        $currentDate = $game->current_date;
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
                'age' => $p->age($currentDate),
                'overallScore' => $p->overall_score,
                'fitness' => $p->fitness,
                'morale' => $p->morale,
                'minuteEntered' => 0,
            ])
            ->sortBy('positionSort')
            ->values()
            ->all();

        // Batch load suspended player IDs for this competition
        $suspendedPlayerIds = PlayerSuspension::suspendedPlayerIdsForCompetition($playerMatch->competition_id);

        // Bench players (all squad players NOT in the starting lineup, not suspended, not injured)
        $matchDate = $playerMatch->scheduled_date;
        $benchPlayers = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->whereNotIn('id', $userLineupIds)
            ->whereNotIn('id', $suspendedPlayerIds)
            ->where(function ($q) use ($matchDate) {
                $q->whereNull('injury_until')
                    ->orWhere('injury_until', '<', $matchDate);
            })
            ->when($game->requiresSquadEnrollment(), fn ($q) => $q->whereNotNull('number'))
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
                'age' => $p->age($currentDate),
                'overallScore' => $p->overall_score,
                'fitness' => $p->fitness,
                'morale' => $p->morale,
                'minuteEntered' => null,
            ])
            ->sortBy('positionSort')
            ->values()
            ->all();

        // Both teams' starting lineups for the Lineups tab
        $mapLineup = fn (array $ids) => GamePlayer::with('player')
            ->whereIn('id', $ids)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->player->name ?? '',
                'positionAbbr' => PositionMapper::toAbbreviation($p->position),
                'positionGroup' => $p->position_group,
                'positionSort' => LineupService::positionSortOrder($p->position),
                'overallScore' => $p->overall_score,
            ])
            ->sortBy('positionSort')
            ->values()
            ->all();

        $homeLineupDisplay = $mapLineup($playerMatch->home_lineup ?? []);
        $awayLineupDisplay = $mapLineup($playerMatch->away_lineup ?? []);

        // User's current tactical setup
        $userFormation = $isUserHome
            ? ($playerMatch->home_formation ?? Formation::F_4_3_3->value)
            : ($playerMatch->away_formation ?? Formation::F_4_3_3->value);
        $userMentality = $isUserHome
            ? ($playerMatch->home_mentality ?? Mentality::BALANCED->value)
            : ($playerMatch->away_mentality ?? Mentality::BALANCED->value);

        $availableFormations = array_map(fn ($f) => [
            'value' => $f->value,
            'label' => $f->label(),
            'tooltip' => $f->tooltip(),
        ], Formation::cases());
        $availableMentalities = array_map(fn ($m) => [
            'value' => $m->value,
            'label' => $m->label(),
            'tooltip' => $m->tooltip(),
        ], Mentality::cases());

        // Tournament context
        $isTournamentMode = $game->isTournamentMode();
        $isTournamentKnockout = $isTournamentMode && $playerMatch->cup_tie_id !== null;
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

        // Opponent's tactical setup (for tactical commentary)
        $oppPrefix = $isUserHome ? 'away' : 'home';
        $opponentPlayingStyle = $playerMatch->{"{$oppPrefix}_playing_style"} ?? 'balanced';
        $opponentPressing = $playerMatch->{"{$oppPrefix}_pressing"} ?? 'standard';
        $opponentDefLine = $playerMatch->{"{$oppPrefix}_defensive_line"} ?? 'normal';
        $opponentMentality = $playerMatch->{"{$oppPrefix}_mentality"} ?? 'balanced';

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

        // Pitch visualization data for tactical panel
        $formationSlots = [];
        foreach (Formation::cases() as $formation) {
            $formationSlots[$formation->value] = array_map(function ($slot) {
                $slot['displayLabel'] = PositionSlotMapper::slotToDisplayAbbreviation($slot['label']);

                return $slot;
            }, $formation->pitchSlots());
        }

        $teamColorsHex = TeamColors::toHex($game->team->colors ?? TeamColors::get($game->team->getRawOriginal('name')));
        $slotCompatibility = PositionSlotMapper::SLOT_COMPATIBILITY;
        $gridConfig = PitchGrid::getGridConfig();

        // Narrative templates for client-side atmosphere generation
        $narrativeTemplates = [
            'shotOnTarget' => __('commentary.atmosphere_shot_on_target'),
            'shotOffTarget' => __('commentary.atmosphere_shot_off_target'),
            'foul' => __('commentary.atmosphere_foul'),
            'contextualDrawOpen' => __('commentary.contextual_draw_open'),
            'contextualDrawWithGoals' => __('commentary.contextual_draw_with_goals'),
            'contextualHomeLeading' => __('commentary.contextual_home_leading'),
            'contextualAwayLeading' => __('commentary.contextual_away_leading'),
            'contextualHomeDominant' => __('commentary.contextual_home_dominant'),
            'contextualAwayDominant' => __('commentary.contextual_away_dominant'),
            'contextualTightGame' => __('commentary.contextual_tight_game'),
            'contextualEndLosing' => __('commentary.contextual_end_losing'),
            'contextualEndLosingByOne' => __('commentary.contextual_end_losing_by_one'),
            'contextualEndWinning' => __('commentary.contextual_end_winning'),
            'contextualEndDraw' => __('commentary.contextual_end_draw'),
            'contextualSecondHalfStart' => __('commentary.contextual_second_half_start'),
            'contextualAwayFans' => __('commentary.contextual_away_fans'),
            'contextualHomeFans' => __('commentary.contextual_home_fans'),
            'contextualHighFouls' => __('commentary.contextual_high_fouls'),
            'goalAssisted' => __('commentary.goal_assisted'),
            'goalSolo' => __('commentary.goal_solo'),
            // Tactical narratives
            'tacticalHighPressWorking' => __('commentary.tactical_high_press_working'),
            'tacticalHighPressFading' => __('commentary.tactical_high_press_fading'),
            'tacticalHighPressExhausted' => __('commentary.tactical_high_press_exhausted'),
            'tacticalOppPressFading' => __('commentary.tactical_opp_press_fading'),
            'tacticalOppExhausted' => __('commentary.tactical_opp_exhausted'),
            'tacticalLowBlockWall' => __('commentary.tactical_low_block_wall'),
            'tacticalLowBlockFresh' => __('commentary.tactical_low_block_fresh'),
            'tacticalPossessionControl' => __('commentary.tactical_possession_control'),
            'tacticalPossessionFrustrated' => __('commentary.tactical_possession_frustrated'),
            'tacticalCounterWaiting' => __('commentary.tactical_counter_waiting'),
            'tacticalCounterExploiting' => __('commentary.tactical_counter_exploiting'),
            'tacticalDirectPlay' => __('commentary.tactical_direct_play'),
            'tacticalDirectBypassingPress' => __('commentary.tactical_direct_bypassing_press'),
            // Tactical goal flavoring
            'goalCounterAttack' => __('commentary.goal_counter_attack'),
            'goalPossession' => __('commentary.goal_possession'),
            'goalDirect' => __('commentary.goal_direct'),
        ];

        return view('live-match', [
            'game' => $game,
            'match' => $playerMatch,
            'events' => $events,
            'otherMatches' => $otherMatches,
            'resultsUrl' => $resultsUrl,
            'lineupPlayers' => $lineupPlayers,
            'benchPlayers' => $benchPlayers,
            'tacticalActionsUrl' => route('game.match.tactical-actions', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
            'extraTimeUrl' => route('game.match.extra-time', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
            'penaltiesUrl' => route('game.match.penalties', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
            'userFormation' => $userFormation,
            'userMentality' => $userMentality,
            'availableFormations' => $availableFormations,
            'availableMentalities' => $availableMentalities,
            'userPlayingStyle' => $userPlayingStyle,
            'userPressing' => $userPressing,
            'userDefLine' => $userDefLine,
            'opponentPlayingStyle' => $opponentPlayingStyle,
            'opponentPressing' => $opponentPressing,
            'opponentDefLine' => $opponentDefLine,
            'opponentMentality' => $opponentMentality,
            'availablePlayingStyles' => $availablePlayingStyles,
            'availablePressing' => $availablePressing,
            'availableDefLine' => $availableDefLine,
            'isKnockout' => $isKnockout,
            'extraTimeData' => $extraTimeData,
            'twoLeggedInfo' => $twoLeggedInfo,
            'isTournamentMode' => $isTournamentMode,
            'isTournamentKnockout' => $isTournamentKnockout,
            'knockoutRoundNumber' => $knockoutRoundNumber,
            'knockoutRoundName' => $knockoutRoundName,
            'processingStatusUrl' => $game->isCareerMode()
                ? route('game.setup-status', $game->id)
                : null,
            'homePossession' => $playerMatch->home_possession ?? 50,
            'awayPossession' => $playerMatch->away_possession ?? 50,
            'mvpPlayerName' => $playerMatch->mvpPlayer?->player?->name,
            'mvpPlayerTeamId' => $playerMatch->mvpPlayer?->team_id,
            'homeLineupDisplay' => $homeLineupDisplay,
            'awayLineupDisplay' => $awayLineupDisplay,
            'homeFormation' => $playerMatch->home_formation ?? Formation::F_4_3_3->value,
            'awayFormation' => $playerMatch->away_formation ?? Formation::F_4_3_3->value,
            'formationSlots' => $formationSlots,
            'teamColors' => $teamColorsHex,
            'slotCompatibility' => $slotCompatibility,
            'gridConfig' => $gridConfig,
            'pitchPositions' => $playerMatch->{"{$prefix}_pitch_positions"}
                ?? $game->tactics?->default_pitch_positions ?? [],
            'slotAssignments' => $playerMatch->{"{$prefix}_slot_assignments"}
                ?? $game->tactics?->default_slot_assignments ?? [],
            'narrativeTemplates' => $narrativeTemplates,
        ]);
    }

    /**
     * Build ET data for page-refresh scenario (ET already simulated).
     */
    private function buildExtraTimeData(GameMatch $match): array
    {
        $etEvents = $match->events
            ->filter(fn ($e) => $e->minute > 93);

        $formattedEvents = MatchResimulationService::formatMatchEvents($etEvents);

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
