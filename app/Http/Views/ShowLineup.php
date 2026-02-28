<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CalendarService;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\LineupService;
use App\Models\ClubProfile;
use App\Models\Game;
use App\Support\PitchGrid;
use App\Support\PositionMapper;
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
        $game = Game::with(['team', 'tactics'])->findOrFail($gameId);
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
        $defaultFormation = $game->tactics?->default_formation ?? '4-4-2';
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
            // Use previous formation if available, otherwise default
            $currentFormation = $currentFormation ?? $previous['formation'] ?? $defaultFormation;
            $currentSlotAssignments = $game->tactics?->default_slot_assignments;
        }

        $currentFormation = $currentFormation ?? $defaultFormation;
        $formationEnum = Formation::tryFrom($currentFormation) ?? Formation::F_4_4_2;

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

        // Prepare pitch slots for each formation, adding Spanish display labels
        $formationSlots = [];
        foreach (Formation::cases() as $formation) {
            $formationSlots[$formation->value] = array_map(function ($slot) {
                $slot['displayLabel'] = PositionMapper::slotToDisplayAbbreviation($slot['label']);

                return $slot;
            }, $formation->pitchSlots());
        }

        // Pass slot compatibility matrix to JavaScript
        $slotCompatibility = PositionSlotMapper::SLOT_COMPATIBILITY;

        // User's best XI average for coach assistant comparison
        $userBestXI = $this->lineupService->getBestXIWithAverage($gameId, $game->team_id, $matchDate, $competitionId);
        $userTeamAverage = $userBestXI['average'];

        // Get opponent scouting data (including predicted formation and mentality)
        $opponentData = $this->getOpponentData($gameId, $opponent->id, $matchDate, $competitionId, !$isHome, $userTeamAverage);

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
            'currentSlotAssignments' => $game->tactics?->default_slot_assignments,
            'autoLineup' => $autoLineup,
            'formations' => Formation::cases(),
            'currentFormation' => $currentFormation,
            'defaultFormation' => $defaultFormation,
            'mentalities' => Mentality::cases(),
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
            'gridConfig' => $gridConfig,
            'currentPitchPositions' => $currentPitchPositions,
        ]);
    }

    /**
     * Get opponent scouting data, including predicted formation and mentality.
     *
     * @param bool $opponentIsHome Whether the opponent is the home team
     * @param int $userTeamAverage The user's best XI average for relative strength comparison
     */
    private function getOpponentData(string $gameId, string $opponentTeamId, $matchDate, string $competitionId, bool $opponentIsHome, int $userTeamAverage): array
    {
        // Get opponent's available players and best XI
        $availablePlayers = $this->lineupService->getAvailablePlayers($gameId, $opponentTeamId, $matchDate, $competitionId);

        // Predict their formation based on squad composition
        $predictedFormation = $this->lineupService->selectAIFormation($availablePlayers);

        // Calculate their best XI average using the predicted formation
        $bestXI = $this->lineupService->selectBestXI($availablePlayers, $predictedFormation);
        $teamAverage = $this->lineupService->calculateTeamAverage($bestXI);

        // Predict their mentality based on reputation and context
        $clubProfile = ClubProfile::where('team_id', $opponentTeamId)->first();
        $predictedMentality = $this->lineupService->selectAIMentality(
            $clubProfile?->reputation_level,
            $opponentIsHome,
            (float) $teamAverage,
            (float) $userTeamAverage
        );

        // Get recent form (last 5 matches) via CalendarService
        $form = $this->calendarService->getTeamForm($gameId, $opponentTeamId);

        return [
            'teamAverage' => $teamAverage,
            'form' => $form,
            'formation' => $predictedFormation->value,
            'mentality' => $predictedMentality->value,
        ];
    }
}
