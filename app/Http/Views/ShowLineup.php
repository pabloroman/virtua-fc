<?php

namespace App\Http\Views;

use App\Game\Enums\Formation;
use App\Game\Enums\Mentality;
use App\Game\Services\LineupService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
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

        // Get match date and competition for availability checks
        $matchDate = $match->scheduled_date;
        $competitionId = $match->competition_id;

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
                $competitionId
            );
            $currentLineup = $previous['lineup'];
            // Use previous formation if available, otherwise default
            $currentFormation = $currentFormation ?? $previous['formation'] ?? $defaultFormation;
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

        $currentLineup = $currentLineup ?? [];

        // Batch load suspended player IDs for this competition (single query, avoids N+1)
        $suspendedPlayerIds = PlayerSuspension::where('competition_id', $competitionId)
            ->where('matches_remaining', '>', 0)
            ->whereIn('game_player_id', $allPlayers->pluck('id'))
            ->pluck('game_player_id')
            ->toArray();

        // Prepare player data for JavaScript (flat array with all needed info)
        $playersData = $allPlayers->map(function ($p) use ($matchDate, $suspendedPlayerIds) {
            // Check availability using pre-loaded suspension data
            $isSuspended = in_array($p->id, $suspendedPlayerIds);
            $isInjured = $p->injury_until && $matchDate && $p->injury_until->gt($matchDate);
            $isAvailable = !$isSuspended && !$isInjured;

            return [
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
                'isAvailable' => $isAvailable,
            ];
        })->keyBy('id')->toArray();

        // Prepare pitch slots for each formation
        $formationSlots = [];
        foreach (Formation::cases() as $formation) {
            $formationSlots[$formation->value] = $formation->pitchSlots();
        }

        // Pass slot compatibility matrix to JavaScript
        $slotCompatibility = PositionSlotMapper::SLOT_COMPATIBILITY;

        // Get opponent scouting data
        $opponentData = $this->getOpponentData($gameId, $opponent->id, $matchDate, $competitionId);

        return view('lineup', [
            'game' => $game,
            'match' => $match,
            'isHome' => $isHome,
            'opponent' => $opponent,
            'competitionId' => $competitionId,
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
            'mentalities' => Mentality::cases(),
            'currentMentality' => $currentMentality,
            'defaultMentality' => $defaultMentality,
            'playersData' => $playersData,
            'formationSlots' => $formationSlots,
            'slotCompatibility' => $slotCompatibility,
            'opponentData' => $opponentData,
        ]);
    }

    /**
     * Get opponent scouting data.
     */
    private function getOpponentData(string $gameId, string $opponentTeamId, $matchDate, string $competitionId): array
    {
        // Get opponent's players
        $opponentPlayers = GamePlayer::with('player')
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

        // Count unavailable players (batch load suspensions to avoid N+1)
        $injuredCount = $opponentPlayers->filter(fn($p) => $p->isInjured($matchDate))->count();
        $suspendedPlayerIds = PlayerSuspension::where('competition_id', $competitionId)
            ->where('matches_remaining', '>', 0)
            ->whereIn('game_player_id', $opponentPlayers->pluck('id'))
            ->pluck('game_player_id')
            ->toArray();
        $suspendedCount = count($suspendedPlayerIds);

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
