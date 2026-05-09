<?php

namespace App\Http\Views;

use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;

use App\Support\PitchGrid;
use App\Support\PositionSlotMapper;
use App\Support\PreMatchContext;
use App\Support\TeamColors;

class ShowLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(string $gameId)
    {
        $context = PreMatchContext::resolve($gameId, ['team', 'tactics', 'tacticalPresets']);
        $game = $context->game;
        $match = $context->match;
        $isHome = $context->isHome;
        $opponent = $context->opponent;
        $matchDate = $context->matchDate;
        $competitionId = $context->competitionId;

        // Get all players (including unavailable for display), sorted and grouped
        $playersByGroup = $this->lineupService->getPlayersByPositionGroup($gameId, $game->team_id);
        $allPlayers = $playersByGroup['all'];

        // Get current lineup if any
        $currentLineup = $this->lineupService->getLineup($match, $game->team_id);
        $requireEnrollment = $game->requiresSquadEnrollment();

        // Get formation
        $defaultFormation = $game->tactics?->default_formation ?? Formation::F_4_3_3->value;
        $currentFormation = $this->lineupService->getFormation($match, $game->team_id);

        // If no lineup set, try to prefill from previous match
        if (empty($currentLineup)) {
            $previous = $this->lineupService->getPreviousLineup(
                $gameId,
                $game->team_id,
                $match->id,
                $matchDate,
                $competitionId,
                $requireEnrollment,
            );
            $currentLineup = $previous['lineup'];
        }

        $currentFormation = $currentFormation ?? $defaultFormation;
        $formationEnum = Formation::tryFrom($currentFormation) ?? Formation::F_4_3_3;

        // Get mentality
        $defaultMentality = $game->tactics?->default_mentality ?? 'balanced';
        $currentMentality = $this->lineupService->getMentality($match, $game->team_id);
        $currentMentality = $currentMentality ?? $defaultMentality;

        // Get auto-selected lineup for quick select (using current formation)
        $autoLineup = $this->lineupService->autoSelectLineup($gameId, $game->team_id, $matchDate, $competitionId, $formationEnum, $requireEnrollment);

        // If still no lineup (first match ever), use auto lineup
        if (empty($currentLineup)) {
            $currentLineup = $autoLineup;
        }

        // Prepare player data for JavaScript (flat array with all needed info)
        // Suspensions are eager-loaded via getAllPlayers, so no extra queries needed
        $playersData = $allPlayers->map(function ($p) use ($matchDate, $competitionId, $requireEnrollment) {
            $isAvailable = $p->isAvailable($matchDate, $competitionId);
            if ($isAvailable && $requireEnrollment && $p->number === null) {
                $isAvailable = false;
            }

            return [
                'id' => $p->id,
                'name' => $p->name,
                'number' => $p->number,
                'position' => $p->position,
                'positionGroup' => $p->position_group,
                'positionAbbr' => $p->position_abbreviation,
                'positions' => $p->positions,
                'overallScore' => $p->getEffectiveRating(),
                'rawOverall' => $p->overall_score,
                'fitness' => $p->fitness,
                'morale' => $p->morale,
                'isAvailable' => $isAvailable,
            ];
        })->keyBy('id')->toArray();

        // Filter stale player IDs from lineups (e.g. players sold after lineup was saved)
        $validPlayerIds = array_keys($playersData);
        if (! empty($currentLineup)) {
            $currentLineup = array_values(array_intersect($currentLineup, $validPlayerIds));
        }

        // Resolve the authoritative slot map for this match. If the match row
        // already has a persisted map, use it; otherwise lazily compute from
        // the stored lineup + formation (no persistence on the read path).
        // Falls back to the team's default_slot_assignments for brand-new
        // games where the match has no lineup yet.
        $currentSlotMap = $this->lineupService->resolveSlotAssignments($match, $game->team_id);
        if (empty($currentSlotMap) && ! empty($game->tactics?->default_slot_assignments)) {
            $currentSlotMap = $game->tactics->default_slot_assignments;
        }
        if (! empty($currentSlotMap)) {
            $currentSlotMap = array_filter(
                $currentSlotMap,
                fn ($playerId) => in_array($playerId, $validPlayerIds),
            );
        }

        // Prepare pitch slots for each formation, adding Spanish display labels
        $formationSlots = [];
        foreach (Formation::cases() as $formation) {
            $formationSlots[$formation->value] = array_map(function ($slot) {
                $slot['displayLabel'] = PositionSlotMapper::slotToDisplayAbbreviation($slot['label']);

                return $slot;
            }, $formation->pitchSlots());
        }

        // Pass slot compatibility matrix to JavaScript
        $slotCompatibility = PositionSlotMapper::SLOT_COMPATIBILITY;

        // User's best XI average for coach assistant comparison + opponent prediction.
        $userTeamAverage = $this->lineupService
            ->getBestXIWithAverage($gameId, $game->team_id, $matchDate, $competitionId, requireEnrollment: $requireEnrollment)['average'];

        // Get opponent scouting data (including predicted formation, mentality, and instructions)
        $opponentData = $this->lineupService->predictOpponentTactics($gameId, $opponent->id, $matchDate, $competitionId, $match->hasHomeAdvantage($opponent->id), $userTeamAverage);

        // Formation modifiers for coach assistant tips (attack/defense per formation)
        $formationModifiers = [];
        foreach (Formation::cases() as $formation) {
            $formationModifiers[$formation->value] = [
                'attack' => $formation->attackModifier(),
                'defense' => $formation->defenseModifier(),
            ];
        }

        // Team shirt colors for pitch visualization
        $teamColorsHex = TeamColors::toHex($game->team->colors ?? TeamColors::get($game->team->getRawOriginal('name')));
        $opponentColorsHex = TeamColors::toHex($opponent->colors ?? TeamColors::get($opponent->getRawOriginal('name')));

        // Instruction defaults and available options
        $defaultPlayingStyle = $game->tactics?->default_playing_style ?? 'balanced';
        $defaultPressing = $game->tactics?->default_pressing ?? 'standard';
        $defaultDefLine = $game->tactics?->default_defensive_line ?? 'normal';

        $formationOptions = array_map(fn (Formation $f) => [
            'value' => $f->value,
            'label' => $f->label(),
            'tooltip' => $f->tooltip(),
        ], Formation::cases());

        $mentalityOptions = array_map(fn (Mentality $m) => [
            'value' => $m->value,
            'label' => $m->label(),
            'tooltip' => $m->tooltip(),
            'summary' => $m->summary(),
        ], Mentality::cases());

        $playingStyles = array_map(fn (PlayingStyle $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'tooltip' => $s->tooltip(),
            'summary' => $s->summary(),
        ], PlayingStyle::cases());

        $pressingOptions = array_map(fn (PressingIntensity $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'tooltip' => $p->tooltip(),
            'summary' => $p->summary(),
        ], PressingIntensity::cases());

        $defensiveLineOptions = array_map(fn (DefensiveLineHeight $d) => [
            'value' => $d->value,
            'label' => $d->label(),
            'tooltip' => $d->tooltip(),
            'summary' => $d->summary(),
        ], DefensiveLineHeight::cases());

        // xG preview config: all modifiers needed for frontend calculation
        $xgConfig = [
            'base_goals' => config('match_simulation.base_goals', 1.3),
            'skill_dominance' => config('match_simulation.skill_dominance', 2.0),
            'home_advantage_goals' => $match->isNeutralVenue()
                ? 0.0
                : config('match_simulation.home_advantage_goals', 0.15),
            'mentalities' => config('match_simulation.mentalities'),
            'playing_styles' => collect(config('match_simulation.playing_styles'))->map(fn ($s) => [
                'own_xg' => $s['own_xg'],
                'opp_xg' => $s['opp_xg'],
            ])->all(),
            'pressing' => collect(config('match_simulation.pressing'))->map(fn ($p) => [
                'opp_xg' => $p['opp_xg'],
            ])->all(),
            'defensive_line' => collect(config('match_simulation.defensive_line'))->map(fn ($d) => [
                'own_xg' => $d['own_xg'],
                'opp_xg' => $d['opp_xg'],
            ])->all(),
            'tactical_interactions' => config('match_simulation.tactical_interactions'),
        ];

        // Pitch grid config for advanced positioning
        $gridConfig = PitchGrid::getGridConfig();
        $currentPitchPositions = $game->tactics?->default_pitch_positions;

        return view('lineup', [
            'game' => $game,
            'match' => $match,
            'isHome' => $isHome,
            'opponent' => $opponent,
            'competitionId' => $competitionId,
            'matchDate' => $matchDate,
            'goalkeepers' => $playersByGroup['goalkeepers'],
            'defenders' => $playersByGroup['defenders'],
            'midfielders' => $playersByGroup['midfielders'],
            'forwards' => $playersByGroup['forwards'],
            'currentLineup' => $currentLineup,
            'currentSlotMap' => ! empty($currentSlotMap) ? $currentSlotMap : (object) [],
            'computeSlotsUrl' => route('game.lineup.computeSlots', $game->id),
            'autoLineup' => $autoLineup,
            'formationOptions' => $formationOptions,
            'currentFormation' => $currentFormation,
            'defaultFormation' => $defaultFormation,
            'mentalityOptions' => $mentalityOptions,
            'currentMentality' => $currentMentality,
            'defaultMentality' => $defaultMentality,
            'playersData' => $playersData,
            'formationSlots' => $formationSlots,
            'slotCompatibility' => $slotCompatibility,
            'opponentData' => $opponentData,
            'opponentColors' => $opponentColorsHex,
            'teamColors' => $teamColorsHex,
            'userTeamAverage' => $userTeamAverage,
            'formationModifiers' => $formationModifiers,
            'playingStyles' => $playingStyles,
            'pressingOptions' => $pressingOptions,
            'defensiveLineOptions' => $defensiveLineOptions,
            'currentPlayingStyle' => $defaultPlayingStyle,
            'currentPressing' => $defaultPressing,
            'currentDefLine' => $defaultDefLine,
            'xgConfig' => $xgConfig,
            'gridConfig' => $gridConfig,
            'currentPitchPositions' => $currentPitchPositions,

            'tacticalPresets' => $game->tacticalPresets,
            'presetsConfig' => $game->tacticalPresets->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'formation' => $p->formation,
                'lineup' => collect($p->lineup)->filter(fn ($id) => in_array($id, $validPlayerIds))->sort()->values()->all(),
                'mentality' => $p->mentality,
                'playing_style' => $p->playing_style,
                'pressing' => $p->pressing,
                'defensive_line' => $p->defensive_line,
                'slot_assignments' => ! empty($p->slot_assignments)
                    ? array_filter($p->slot_assignments, fn ($playerId) => in_array($playerId, $validPlayerIds))
                    : null,
                'pitch_positions' => $p->pitch_positions,
            ])->values(),
        ]);
    }

}
