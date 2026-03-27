<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use App\Modules\Player\Services\InjuryService;
use App\Models\Game;
use App\Support\PitchGrid;
use App\Support\PositionSlotMapper;
use App\Support\TeamColors;

class ShowLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
        private readonly CalendarService $calendarService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'tactics', 'tacticalPresets'])->findOrFail($gameId);
        $match = $game->next_match;

        abort_unless($match, 404);

        $match->load(['homeTeam', 'awayTeam', 'competition']);

        // Determine if user is home or away
        $isHome = $match->home_team_id === $game->team_id;
        $opponent = $isHome ? $match->awayTeam : $match->homeTeam;

        // Get all players (including unavailable for display), sorted and grouped
        $playersByGroup = $this->lineupService->getPlayersByPositionGroup($gameId, $game->team_id);
        $allPlayers = $playersByGroup['all'];

        // Get match date and competition for availability checks
        $matchDate = $match->scheduled_date;
        $competitionId = $match->competition_id;

        // Get current lineup if any
        $currentLineup = $this->lineupService->getLineup($match, $game->team_id);

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
                $competitionId
            );
            $currentLineup = $previous['lineup'];
            $currentSlotAssignments = $game->tactics?->default_slot_assignments;
        }

        $currentFormation = $currentFormation ?? $defaultFormation;
        $formationEnum = Formation::tryFrom($currentFormation) ?? Formation::F_4_3_3;

        // Get mentality
        $defaultMentality = $game->tactics?->default_mentality ?? 'balanced';
        $currentMentality = $this->lineupService->getMentality($match, $game->team_id);
        $currentMentality = $currentMentality ?? $defaultMentality;

        // Get auto-selected lineup for quick select (using current formation)
        $autoLineup = $this->lineupService->autoSelectLineup($gameId, $game->team_id, $matchDate, $competitionId, $formationEnum);

        // If still no lineup (first match ever), use auto lineup
        if (empty($currentLineup)) {
            $currentLineup = $autoLineup;
        }

        // Prepare player data for JavaScript (flat array with all needed info)
        // Suspensions are eager-loaded via getAllPlayers, so no extra queries needed
        $playersData = $allPlayers->map(function ($p) use ($matchDate, $competitionId) {
            $isAvailable = $p->isAvailable($matchDate, $competitionId);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'number' => $p->number,
                'position' => $p->position,
                'positionGroup' => $p->position_group,
                'positionAbbr' => $p->position_abbreviation,
                'overallScore' => $p->overall_score,
                'technicalAbility' => $p->technical_ability,
                'physicalAbility' => $p->physical_ability,
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

        // User's best XI average for coach assistant comparison
        $userBestXI = $this->lineupService->getBestXIWithAverage($gameId, $game->team_id, $matchDate, $competitionId);
        $userTeamAverage = $userBestXI['average'];

        // Get opponent scouting data (including predicted formation, mentality, and instructions)
        $opponentData = $this->lineupService->predictOpponentTactics($gameId, $opponent->id, $matchDate, $competitionId, !$isHome, $userTeamAverage);

        // Radar chart data for coach assistant
        $userRadar = $this->calculateRadarValues($userBestXI['players']);
        $opponentRadar = $this->calculateRadarValues($opponentData['bestXIPlayers']);

        // Formation modifiers for coach assistant tips (attack/defense per formation)
        $formationModifiers = [];
        foreach (Formation::cases() as $formation) {
            $formationModifiers[$formation->value] = [
                'attack' => $formation->attackModifier(),
                'defense' => $formation->defenseModifier(),
            ];
        }

        // User's team form for coach assistant display
        $playerForm = $this->calendarService->getTeamForm($gameId, $game->team_id);

        // Team shirt colors for pitch visualization
        $teamColorsHex = TeamColors::toHex($game->team->colors ?? TeamColors::get($game->team->getRawOriginal('name')));

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

        // Tactical guide data (for inline modal)
        $guideFormations = collect(config('match_simulation.formations'))->map(fn ($mods, $name) => [
            'name' => $name,
            'attack' => $mods['attack'],
            'defense' => $mods['defense'],
        ])->values();

        $guideMentalities = collect(config('match_simulation.mentalities'))->map(fn ($mods, $name) => [
            'name' => $name,
            'own_goals' => $mods['own_goals'],
            'opponent_goals' => $mods['opponent_goals'],
        ])->values();

        $guidePlayingStyles = collect(PlayingStyle::cases())->map(fn (PlayingStyle $s) => [
            'label' => $s->label(),
            'own_xg' => $s->ownXGModifier(),
            'opp_xg' => $s->opponentXGModifier(),
            'energy' => $s->energyDrainMultiplier(),
        ]);

        $guidePressingOptions = collect(PressingIntensity::cases())->map(fn (PressingIntensity $p) => [
            'label' => $p->label(),
            'own_xg' => $p->ownXGModifier(),
            'opp_xg' => config("match_simulation.pressing.{$p->value}.opp_xg"),
            'energy' => $p->energyDrainMultiplier(),
            'fades' => config("match_simulation.pressing.{$p->value}.fade_after") !== null,
            'fade_to' => config("match_simulation.pressing.{$p->value}.fade_opp_xg"),
        ]);

        $guideDefensiveLines = collect(DefensiveLineHeight::cases())->map(fn (DefensiveLineHeight $d) => [
            'label' => $d->label(),
            'own_xg' => $d->ownXGModifier(),
            'opp_xg' => $d->opponentXGModifier(),
            'threshold' => $d->physicalThreshold(),
        ]);

        $tacticalInteractions = config('match_simulation.tactical_interactions');

        // xG preview config: all modifiers needed for frontend calculation
        $xgConfig = [
            'base_goals' => config('match_simulation.base_goals', 1.3),
            'ratio_exponent' => config('match_simulation.ratio_exponent', 2.0),
            'home_advantage_goals' => config('match_simulation.home_advantage_goals', 0.15),
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
                'physical_threshold' => $d['physical_threshold'],
            ])->all(),
            'tactical_interactions' => config('match_simulation.tactical_interactions'),
        ];

        // Pitch grid config for advanced positioning
        $gridConfig = PitchGrid::getGridConfig();
        $currentPitchPositions = $game->tactics?->default_pitch_positions;

        // Pre-compute matches missed for injured players
        $matchesMissedMap = InjuryService::getMatchesMissedMap($gameId, $game->team_id, $matchDate, $allPlayers);

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
            'currentSlotAssignments' => ! empty($game->tactics?->default_slot_assignments)
                ? array_filter($game->tactics->default_slot_assignments, fn ($playerId) => in_array($playerId, $validPlayerIds))
                : null,
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
            'teamColors' => $teamColorsHex,
            'userTeamAverage' => $userTeamAverage,
            'formationModifiers' => $formationModifiers,
            'playerForm' => $playerForm,
            'playingStyles' => $playingStyles,
            'pressingOptions' => $pressingOptions,
            'defensiveLineOptions' => $defensiveLineOptions,
            'currentPlayingStyle' => $defaultPlayingStyle,
            'currentPressing' => $defaultPressing,
            'currentDefLine' => $defaultDefLine,
            'guideFormations' => $guideFormations,
            'guideMentalities' => $guideMentalities,
            'guidePlayingStyles' => $guidePlayingStyles,
            'guidePressingOptions' => $guidePressingOptions,
            'guideDefensiveLines' => $guideDefensiveLines,
            'tacticalInteractions' => $tacticalInteractions,
            'xgConfig' => $xgConfig,
            'gridConfig' => $gridConfig,
            'currentPitchPositions' => $currentPitchPositions,
            'userRadar' => $userRadar,
            'opponentRadar' => $opponentRadar,
            'matchesMissedMap' => $matchesMissedMap,
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

    /**
     * Calculate radar chart values from a collection of best XI players.
     * Returns 8 axes: GK, DEF, MID, FWD averages + fitness, morale, technical, physical.
     *
     * @return array<string, int>
     */
    private function calculateRadarValues(\Illuminate\Support\Collection $players): array
    {
        if ($players->isEmpty()) {
            return array_fill_keys(['goalkeeper', 'defense', 'midfield', 'attack', 'fitness', 'morale', 'technical', 'physical'], 0);
        }

        $grouped = $players->groupBy(fn ($p) => $p->position_group);

        $avgOverall = fn (string $group) => (int) round(
            ($grouped->get($group) ?? collect())->avg('overall_score') ?? 0
        );

        return [
            'goalkeeper' => $avgOverall('Goalkeeper'),
            'defense' => $avgOverall('Defender'),
            'midfield' => $avgOverall('Midfielder'),
            'attack' => $avgOverall('Forward'),
            'fitness' => (int) round($players->avg('fitness')),
            'morale' => (int) round($players->avg('morale')),
            'technical' => (int) round($players->avg('technical_ability')),
            'physical' => (int) round($players->avg('physical_ability')),
        ];
    }
}
