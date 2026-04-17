<?php

namespace App\Http\Views;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use Illuminate\Support\Facades\Cache;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\MatchAttendance;
use App\Models\PlayerSuspension;
use App\Modules\Match\Services\ExtraTimeAndPenaltyService;
use App\Modules\Match\Services\MatchResimulationService;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Support\LiveMatchLineupPresenter;
use App\Support\LiveMatchNarrativeTemplates;
use App\Support\PitchGrid;
use App\Support\PositionSlotMapper;
use App\Support\TeamColors;

class ShowLiveMatch
{
    public function __construct(
        private readonly LineupService $lineupService,
        private readonly ExtraTimeAndPenaltyService $extraTimeService,
        private readonly MatchAttendanceService $matchAttendanceService,
    ) {}

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

        // Track whether the live animation has already been seen for this match.
        // First visit sets the flag and plays the animation; refreshes skip to full_time.
        $sessionKey = "live_match_animated:{$matchId}";
        $animationSeen = session()->has($sessionKey);
        if (! $animationSeen) {
            session()->put($sessionKey, true);
        }

        // Load team form from standings (for match summary at full time)
        $homeStanding = GameStanding::forTeamInCompetition($game, $playerMatch->home_team_id, $playerMatch->competition_id);
        $awayStanding = GameStanding::forTeamInCompetition($game, $playerMatch->away_team_id, $playerMatch->competition_id);

        // Determine if this is a knockout match (cup tie) that could go to ET
        $isKnockout = $playerMatch->cup_tie_id !== null;
        $extraTimeData = null;

        // If match already has ET data (page refresh during ET), prepare it
        if ($isKnockout && $playerMatch->is_extra_time) {
            $extraTimeData = $this->extraTimeService->buildRefreshState($playerMatch);
        }

        // For two-legged ties, pass first leg aggregate info
        $twoLeggedInfo = null;
        if ($isKnockout) {
            $twoLeggedInfo = CupTie::with('firstLegMatch')->find($playerMatch->cup_tie_id)
                ?->firstLegInfoFor($playerMatch);
        }

        // True when the current match is either leg of a two-legged cup tie.
        // Used to suppress late-game "draw" commentary in two-legged ties,
        // where aggregate scoring (not this single match) determines the
        // winner so "heading to extra time" phrasing would be misleading.
        $isTwoLeggedTie = false;
        if ($isKnockout) {
            $isTwoLeggedTie = CupTie::find($playerMatch->cup_tie_id)?->isTwoLegged() ?? false;
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

        $currentDate = $game->current_date;
        $matchDate = $playerMatch->scheduled_date;
        $suspendedPlayerIds = PlayerSuspension::suspendedPlayerIdsForCompetition($gameId, $playerMatch->competition_id);
        $playerPerformances = Cache::get("match_performances:{$playerMatch->id}", []);

        $opponentTeamId = $isUserHome ? $playerMatch->away_team_id : $playerMatch->home_team_id;
        $opponentLineupIds = $isUserHome
            ? ($playerMatch->away_lineup ?? [])
            : ($playerMatch->home_lineup ?? []);

        $lineupPlayers = LiveMatchLineupPresenter::startingLineup($userLineupIds, $currentDate);
        $benchPlayers = LiveMatchLineupPresenter::userBench(
            $game, $userLineupIds, $suspendedPlayerIds, $matchDate, $currentDate, $playerPerformances
        );
        $opponentBenchPlayers = LiveMatchLineupPresenter::opponentBench(
            $gameId, $opponentTeamId, $opponentLineupIds, $playerPerformances
        );
        $homeLineupDisplay = LiveMatchLineupPresenter::displayRoster($playerMatch->home_lineup ?? [], $playerPerformances);
        $awayLineupDisplay = LiveMatchLineupPresenter::displayRoster($playerMatch->away_lineup ?? [], $playerPerformances);

        // User's current tactical setup
        $userFormation = $isUserHome
            ? ($playerMatch->home_formation ?? Formation::F_4_3_3->value)
            : ($playerMatch->away_formation ?? Formation::F_4_3_3->value);
        $userMentality = $isUserHome
            ? ($playerMatch->home_mentality ?? Mentality::BALANCED->value)
            : ($playerMatch->away_mentality ?? Mentality::BALANCED->value);

        $availableFormations = Formation::options();
        $availableMentalities = Mentality::options();

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

        $availablePlayingStyles = PlayingStyle::options();
        $availablePressing = PressingIntensity::options();
        $availableDefLine = DefensiveLineHeight::options();

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

        $narrativeTemplates = LiveMatchNarrativeTemplates::build();

        // Resolve attendance for the live-match HUD. The orchestrator's pre-match
        // hook normally writes the row before this view loads; the defensive
        // resolveForMatch call covers deep-links and replays (idempotent).
        $attendanceRow = MatchAttendance::where('game_match_id', $playerMatch->id)->first()
            ?? $this->matchAttendanceService->resolveForMatch($playerMatch, $game);
        $attendance = $attendanceRow?->attendance;
        $attendanceCapacity = $attendanceRow?->capacity_at_match;
        $attendancePercent = $attendanceRow?->fillRatePercent();

        return view('live-match', [
            'game' => $game,
            'match' => $playerMatch,
            'attendance' => $attendance,
            'attendanceCapacity' => $attendanceCapacity,
            'attendancePercent' => $attendancePercent,
            'events' => $events,
            'otherMatches' => $otherMatches,
            'resultsUrl' => $resultsUrl,
            'lineupPlayers' => $lineupPlayers,
            'benchPlayers' => $benchPlayers,
            'opponentBenchPlayers' => $opponentBenchPlayers,
            'tacticalActionsUrl' => route('game.match.tactical-actions', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
            'skipToEndUrl' => route('game.match.skip-to-end', ['gameId' => $game->id, 'matchId' => $playerMatch->id]),
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
            'isTwoLeggedTie' => $isTwoLeggedTie,
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
            // Authoritative slot map: persisted value on the match if present,
            // otherwise lazy-computed via LineupService, with a final fallback
            // to the team's default_slot_assignments for brand-new installs.
            'slotAssignments' => $this->lineupService->resolveSlotAssignments($playerMatch, $game->team_id)
                ?: ($game->tactics?->default_slot_assignments ?? []),
            // Endpoint for formation-preview fetches from the tactical panel.
            // Same endpoint the lineup page uses — single source of truth for
            // the placement algorithm.
            'computeSlotsUrl' => route('game.lineup.computeSlots', $game->id),
            'narrativeTemplates' => $narrativeTemplates,
            'animationSeen' => $animationSeen,
            'homeForm' => $homeStanding?->form ? str_split($homeStanding->form) : [],
            'awayForm' => $awayStanding?->form ? str_split($awayStanding->form) : [],
            'homePosition' => $homeStanding?->position,
            'awayPosition' => $awayStanding?->position,
            'competitionRole' => $playerMatch->competition->role,
            'competitionName' => __($playerMatch->competition->name),
        ]);
    }

}
