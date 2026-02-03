<?php

namespace App\Http\Views;

use App\Game\Services\PlayerDevelopmentService;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameStanding;

class ShowSeasonEnd
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        // Check if season is actually complete
        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', 'Season is not complete yet.');
        }

        $competition = Competition::find($game->competition_id);

        // Get final standings
        $standings = GameStanding::with('team')
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->orderBy('position')
            ->get();

        // Get player team's standing
        $playerStanding = $standings->firstWhere('team_id', $game->team_id);

        // Get league champion
        $champion = $standings->first();

        // Get team IDs in this competition
        $competitionTeamIds = CompetitionTeam::where('competition_id', $game->competition_id)
            ->where('season', $game->season)
            ->pluck('team_id');

        // Top scorers in the league
        $topScorers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->whereIn('team_id', $competitionTeamIds)
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->limit(3)
            ->get();

        // Top assisters in the league
        $topAssisters = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->whereIn('team_id', $competitionTeamIds)
            ->where('assists', '>', 0)
            ->orderByDesc('assists')
            ->orderByDesc('goals')
            ->limit(3)
            ->get();

        // Best goalkeeper (minimum 19 appearances - 50% of 38 match season)
        $minGoalkeeperAppearances = 19;
        $bestGoalkeeper = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->whereIn('team_id', $competitionTeamIds)
            ->where('position', 'Goalkeeper')
            ->where('appearances', '>=', $minGoalkeeperAppearances)
            ->get()
            ->sortBy(function ($gk) {
                // Sort by goals conceded per game (lower is better)
                return $gk->appearances > 0
                    ? $gk->goals_conceded / $gk->appearances
                    : 999;
            })
            ->first();

        // Player team stats
        $playerTeamStats = [
            'won' => $playerStanding->won ?? 0,
            'drawn' => $playerStanding->drawn ?? 0,
            'lost' => $playerStanding->lost ?? 0,
            'goalsFor' => $playerStanding->goals_for ?? 0,
            'goalsAgainst' => $playerStanding->goals_against ?? 0,
        ];

        // Get development preview for player's squad
        $squadPlayers = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get()
            ->map(function ($player) {
                $change = $this->developmentService->calculateDevelopment($player);
                $overallBefore = (int) round(($change['techBefore'] + $change['physBefore']) / 2);
                $overallAfter = (int) round(($change['techAfter'] + $change['physAfter']) / 2);
                $overallChange = $overallAfter - $overallBefore;

                return [
                    'player' => $player,
                    'age' => $player->age,
                    'overallBefore' => $overallBefore,
                    'overallAfter' => $overallAfter,
                    'overallChange' => $overallChange,
                    'status' => $player->development_status,
                ];
            })
            ->filter(fn ($item) => $item['overallChange'] !== 0)
            ->sortByDesc(fn ($item) => abs($item['overallChange']))
            ->take(10)
            ->values();

        return view('season-end', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'playerStanding' => $playerStanding,
            'champion' => $champion,
            'topScorers' => $topScorers,
            'topAssisters' => $topAssisters,
            'bestGoalkeeper' => $bestGoalkeeper,
            'playerTeamStats' => $playerTeamStats,
            'developmentPreview' => $squadPlayers,
            'finances' => $game->finances,
        ]);
    }
}
