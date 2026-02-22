<?php

namespace App\Http\Views;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Services\LineupService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Support\PositionMapper;
use App\Support\PositionSlotMapper;
use App\Support\TeamColors;

class ShowLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
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
        $defaultFormation = $game->default_formation ?? '4-4-2';
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
            $currentSlotAssignments = $game->default_slot_assignments;
        }

        $currentFormation = $currentFormation ?? $defaultFormation;
        $formationEnum = Formation::tryFrom($currentFormation) ?? Formation::F_4_4_2;

        // Get mentality
        $defaultMentality = $game->default_mentality ?? 'balanced';
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

        // Get opponent scouting data
        $opponentData = $this->getOpponentData($gameId, $opponent->id, $matchDate, $competitionId);

        // Team shirt colors for pitch visualization
        $teamColorsHex = TeamColors::toHex($game->team->colors ?? TeamColors::get($game->team->getRawOriginal('name')));

        // Formation and mentality modifiers for tactical impact display
        $formationModifiers = [];
        foreach (Formation::cases() as $formation) {
            $formationModifiers[$formation->value] = [
                'attack' => $formation->attackModifier(),
                'defense' => $formation->defenseModifier(),
                'tooltip' => $formation->tooltip(),
            ];
        }
        $mentalityModifiers = [];
        foreach (Mentality::cases() as $mentality) {
            $mentalityModifiers[$mentality->value] = [
                'ownGoals' => $mentality->ownGoalsModifier(),
                'opponentGoals' => $mentality->opponentGoalsModifier(),
                'tooltip' => $mentality->tooltip(),
            ];
        }

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
            'currentSlotAssignments' => $game->default_slot_assignments,
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
            'formationModifiers' => $formationModifiers,
            'mentalityModifiers' => $mentalityModifiers,
        ]);
    }

    /**
     * Get opponent scouting data.
     */
    private function getOpponentData(string $gameId, string $opponentTeamId, $matchDate, string $competitionId): array
    {
        // Get opponent's players
        $opponentPlayers = GamePlayer::with(['player', 'suspensions'])
            ->where('game_id', $gameId)
            ->where('team_id', $opponentTeamId)
            ->get();

        // Calculate their best XI average using LineupService (respects formation requirements)
        $bestXIData = $this->lineupService->getBestXIWithAverage(
            $gameId,
            $opponentTeamId,
            $matchDate,
            $competitionId
        );

        // Get their top scorer
        $topScorer = $opponentPlayers
            ->where('goals', '>', 0)
            ->sortByDesc('goals')
            ->first();

        // Get recent form (last 5 matches)
        $recentMatches = GameMatch::where('game_id', $gameId)
            ->where('played', true)
            ->where(function ($query) use ($opponentTeamId) {
                $query->where('home_team_id', $opponentTeamId)
                    ->orWhere('away_team_id', $opponentTeamId);
            })
            ->orderByDesc('scheduled_date')
            ->limit(5)
            ->get();

        $form = $recentMatches->map(function ($match) use ($opponentTeamId) {
            $isHome = $match->home_team_id === $opponentTeamId;
            $goalsFor = $isHome ? $match->home_score : $match->away_score;
            $goalsAgainst = $isHome ? $match->away_score : $match->home_score;

            if ($goalsFor > $goalsAgainst) {
                return 'W';
            } elseif ($goalsFor < $goalsAgainst) {
                return 'L';
            }
            return 'D';
        })->reverse()->values()->toArray();

        // Count unavailable players (uses eager-loaded suspensions, no extra queries)
        $injuredCount = $opponentPlayers->filter(fn($p) => $p->isInjured($matchDate))->count();
        $suspendedCount = $opponentPlayers->filter(fn($p) => $p->isSuspendedInCompetition($competitionId))->count();

        return [
            'teamAverage' => $bestXIData['average'],
            'topScorer' => $topScorer ? [
                'name' => $topScorer->name,
                'goals' => $topScorer->goals,
            ] : null,
            'form' => $form,
            'injuredCount' => $injuredCount,
            'suspendedCount' => $suspendedCount,
        ];
    }
}
