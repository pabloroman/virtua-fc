<?php

namespace App\Http\Views;

use App\Game\Enums\Formation;
use App\Game\Services\LineupService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Support\PositionSlotMapper;

class ShowLineup
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(string $gameId, string $matchId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        $match = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $gameId)
            ->findOrFail($matchId);

        // Determine if user is home or away
        $isHome = $match->home_team_id === $game->team_id;
        $opponent = $isHome ? $match->awayTeam : $match->homeTeam;

        // Get all players (including unavailable for display)
        $allPlayers = $this->lineupService->getAllPlayers($gameId, $game->team_id);

        // Determine matchday for suspension check
        $matchday = $match->round_number ?? $game->current_matchday + 1;
        $matchDate = $match->scheduled_date;

        // Group and sort players by position
        $players = $allPlayers
            ->sortBy(fn ($p) => $this->positionSortOrder($p->position))
            ->groupBy(fn ($p) => $p->position_group);

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
                $matchId,
                $matchDate,
                $matchday
            );
            $currentLineup = $previous['lineup'];
            // Use previous formation if available, otherwise default
            $currentFormation = $currentFormation ?? $previous['formation'] ?? $defaultFormation;
        }

        $currentFormation = $currentFormation ?? $defaultFormation;
        $formationEnum = Formation::tryFrom($currentFormation) ?? Formation::F_4_4_2;

        // Get auto-selected lineup for quick select (using current formation)
        $autoLineup = $this->lineupService->autoSelectLineup($gameId, $game->team_id, $matchDate, $matchday, $formationEnum);

        // If still no lineup (first match ever), use auto lineup
        if (empty($currentLineup)) {
            $currentLineup = $autoLineup;
        }

        $currentLineup = $currentLineup ?? [];

        // Prepare player data for JavaScript (flat array with all needed info)
        $playersData = $allPlayers->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'position' => $p->position,
            'positionGroup' => $p->position_group,
            'positionAbbr' => $p->position_abbreviation,
            'overallScore' => $p->overall_score,
            'technicalAbility' => $p->technical_ability,
            'physicalAbility' => $p->physical_ability,
            'fitness' => $p->fitness,
            'morale' => $p->morale,
            'isAvailable' => $p->isAvailable($matchDate, $matchday),
        ])->keyBy('id')->toArray();

        // Prepare pitch slots for each formation
        $formationSlots = [];
        foreach (Formation::cases() as $formation) {
            $formationSlots[$formation->value] = $formation->pitchSlots();
        }

        // Pass slot compatibility matrix to JavaScript
        $slotCompatibility = PositionSlotMapper::SLOT_COMPATIBILITY;

        // Get opponent scouting data
        $opponentData = $this->getOpponentData($gameId, $opponent->id, $matchDate, $matchday);

        return view('lineup', [
            'game' => $game,
            'match' => $match,
            'isHome' => $isHome,
            'opponent' => $opponent,
            'matchday' => $matchday,
            'matchDate' => $matchDate,
            'goalkeepers' => $players->get('Goalkeeper', collect()),
            'defenders' => $players->get('Defender', collect()),
            'midfielders' => $players->get('Midfielder', collect()),
            'forwards' => $players->get('Forward', collect()),
            'currentLineup' => $currentLineup,
            'autoLineup' => $autoLineup,
            'formations' => Formation::cases(),
            'currentFormation' => $currentFormation,
            'defaultFormation' => $defaultFormation,
            'playersData' => $playersData,
            'formationSlots' => $formationSlots,
            'slotCompatibility' => $slotCompatibility,
            'opponentData' => $opponentData,
        ]);
    }

    /**
     * Get opponent scouting data.
     */
    private function getOpponentData(string $gameId, string $opponentTeamId, $matchDate, int $matchday): array
    {
        // Get opponent's players
        $opponentPlayers = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $opponentTeamId)
            ->get();

        // Calculate their best XI average (auto-select their best available)
        $availablePlayers = $opponentPlayers->filter(
            fn($p) => $p->isAvailable($matchDate, $matchday)
        );

        // Get best 11 by overall score
        $bestXI = $availablePlayers->sortByDesc('overall_score')->take(11);
        $teamAverage = $bestXI->count() > 0
            ? (int) round($bestXI->avg('overall_score'))
            : 0;

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
            ->orderByDesc('played_at')
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

        // Count unavailable players
        $injuredCount = $opponentPlayers->filter(fn($p) => $p->isInjured($matchDate))->count();
        $suspendedCount = $opponentPlayers->filter(fn($p) => $p->isSuspended($matchday))->count();

        return [
            'teamAverage' => $teamAverage,
            'topScorer' => $topScorer ? [
                'name' => $topScorer->name,
                'goals' => $topScorer->goals,
            ] : null,
            'form' => $form,
            'injuredCount' => $injuredCount,
            'suspendedCount' => $suspendedCount,
        ];
    }

    /**
     * Get sort order for positions within their group.
     */
    private function positionSortOrder(string $position): int
    {
        return match ($position) {
            'Goalkeeper' => 1,
            'Centre-Back' => 10,
            'Left-Back' => 11,
            'Right-Back' => 12,
            'Defensive Midfield' => 20,
            'Central Midfield' => 21,
            'Left Midfield' => 22,
            'Right Midfield' => 23,
            'Attacking Midfield' => 24,
            'Left Winger' => 30,
            'Right Winger' => 31,
            'Second Striker' => 32,
            'Centre-Forward' => 33,
            default => 99,
        };
    }
}
