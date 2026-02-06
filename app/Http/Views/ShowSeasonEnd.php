<?php

namespace App\Http\Views;

use App\Game\Services\PlayerDevelopmentService;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\CupTie;
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
        $game = Game::with('team')->findOrFail($gameId);

        // Check if season is actually complete
        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', 'Season is not complete yet.');
        }

        $competition = Competition::find($game->competition_id);

        // Get final standings for player's league
        $standings = GameStanding::with('team')
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->orderBy('position')
            ->get();

        // Get player team's standing
        $playerStanding = $standings->firstWhere('team_id', $game->team_id);

        // Get league champion
        $champion = $standings->first();

        // Get runner-up
        $runnerUp = $standings->get(1);

        // Get team IDs in this competition
        $competitionTeamIds = CompetitionTeam::where('competition_id', $game->competition_id)
            ->where('season', $game->season)
            ->pluck('team_id');

        // Find the cup competition for this country
        $cupCompetition = Competition::where('country', $competition->country)
            ->where('type', 'cup')
            ->first();

        $cupWinner = null;
        $cupRunnerUp = null;
        $cupName = $cupCompetition?->name ?? 'Cup';

        if ($cupCompetition) {
            // Find the final round (highest round number that was completed)
            $cupFinal = CupTie::with(['winner', 'homeTeam', 'awayTeam'])
                ->where('game_id', $gameId)
                ->where('competition_id', $cupCompetition->id)
                ->where('completed', true)
                ->orderByDesc('round_number')
                ->first();

            $cupWinner = $cupFinal?->winner;
            $cupRunnerUp = $cupFinal ? ($cupFinal->winner_id === $cupFinal->home_team_id
                ? $cupFinal->awayTeam
                : $cupFinal->homeTeam) : null;
        }

        // Find the lower-tier league for promotion display (tier = current tier + 1)
        $lowerTierLeague = Competition::where('country', $competition->country)
            ->where('type', 'league')
            ->where('tier', $competition->tier + 1)
            ->first();

        // Find the higher-tier league for relegation context (tier = current tier - 1)
        $higherTierLeague = Competition::where('country', $competition->country)
            ->where('type', 'league')
            ->where('tier', $competition->tier - 1)
            ->first();

        // Relegated teams (bottom 3 of current league, if tier 1)
        $relegatedTeams = collect();
        if ($competition->tier === 1 && $lowerTierLeague) {
            $relegatedTeams = $standings->where('position', '>=', 18)->values();
        }

        // Promoted teams (if we're in a lower tier, show who got promoted to higher tier)
        $directlyPromoted = collect();
        $playoffWinner = null;
        $promotionTargetLeague = null;

        if ($competition->tier > 1 && $higherTierLeague) {
            // We're in a lower tier, show who got promoted
            $directlyPromoted = $standings->where('position', '<=', 2)->values();
            $promotionTargetLeague = $higherTierLeague;

            // Check for playoff winner in current competition
            $playoffFinal = CupTie::with('winner')
                ->where('game_id', $gameId)
                ->where('competition_id', $competition->id)
                ->where('completed', true)
                ->orderByDesc('round_number')
                ->first();

            $playoffWinner = $playoffFinal?->winner;
        } elseif ($lowerTierLeague) {
            // We're in the top tier, show who got promoted FROM the lower tier
            $lowerTierStandings = GameStanding::with('team')
                ->where('game_id', $gameId)
                ->where('competition_id', $lowerTierLeague->id)
                ->orderBy('position')
                ->get();

            $directlyPromoted = $lowerTierStandings->where('position', '<=', 2)->values();
            $promotionTargetLeague = $competition;

            // Check for playoff winner in lower tier competition
            $playoffFinal = CupTie::with('winner')
                ->where('game_id', $gameId)
                ->where('competition_id', $lowerTierLeague->id)
                ->where('completed', true)
                ->orderByDesc('round_number')
                ->first();

            $playoffWinner = $playoffFinal?->winner;
        }

        // Best attack and defense in the league
        $bestAttack = $standings->sortByDesc('goals_for')->first();
        $bestDefense = $standings->sortBy('goals_against')->first();

        // Manager evaluation
        $managerEvaluation = $this->evaluateManager($playerStanding, $game->currentFinances);

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
            'runnerUp' => $runnerUp,
            'topScorers' => $topScorers,
            'topAssisters' => $topAssisters,
            'bestGoalkeeper' => $bestGoalkeeper,
            'playerTeamStats' => $playerTeamStats,
            'developmentPreview' => $squadPlayers,
            'finances' => $game->currentFinances,
            'investment' => $game->currentInvestment,
            // New data for enhanced season review
            'cupWinner' => $cupWinner,
            'cupRunnerUp' => $cupRunnerUp,
            'cupName' => $cupName,
            'relegatedTeams' => $relegatedTeams,
            'directlyPromoted' => $directlyPromoted,
            'playoffWinner' => $playoffWinner,
            'promotionTargetLeague' => $promotionTargetLeague,
            'lowerTierLeague' => $lowerTierLeague,
            'bestAttack' => $bestAttack,
            'bestDefense' => $bestDefense,
            'managerEvaluation' => $managerEvaluation,
        ]);
    }

    /**
     * Evaluate the manager's performance based on targets.
     */
    private function evaluateManager($playerStanding, $finances): array
    {
        $actualPosition = $playerStanding->position ?? 20;
        $targetPosition = $finances?->projected_position ?? 10;

        $positionDiff = $targetPosition - $actualPosition; // Positive = better than target

        // Determine grade and message
        if ($positionDiff >= 5) {
            $grade = 'exceptional';
            $title = 'Exceptional Season';
            $message = "The board is delighted with your performance. Finishing {$positionDiff} places above expectations has exceeded all targets. Your contract extension is already being prepared.";
        } elseif ($positionDiff >= 2) {
            $grade = 'exceeded';
            $title = 'Exceeded Expectations';
            $message = "An impressive campaign. You've outperformed our projected finish and the board is very pleased with the direction you're taking the club.";
        } elseif ($positionDiff >= -1) {
            $grade = 'met';
            $title = 'Expectations Met';
            $message = "A solid season that met our targets. The board is satisfied with your work and looks forward to continued progress next season.";
        } elseif ($positionDiff >= -4) {
            $grade = 'below';
            $title = 'Below Expectations';
            $message = "A disappointing campaign. We expected better results and the board will be monitoring performance closely next season.";
        } else {
            $grade = 'disaster';
            $title = 'Unacceptable Performance';
            $message = "This season has been a disaster. The board expected a finish around {$targetPosition}th place, but {$actualPosition}th is unacceptable. Significant improvement is required.";
        }

        return [
            'grade' => $grade,
            'title' => $title,
            'message' => $message,
            'actualPosition' => $actualPosition,
            'targetPosition' => $targetPosition,
            'positionDiff' => $positionDiff,
        ];
    }
}
