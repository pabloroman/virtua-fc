<?php

namespace App\Http\Views;

use App\Game\Services\CountryConfig;
use App\Game\Services\PlayerDevelopmentService;
use App\Game\Services\SeasonGoalService;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;

class ShowSeasonEnd
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
        private readonly SeasonGoalService $seasonGoalService,
        private readonly CountryConfig $countryConfig,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Check if season is actually complete (only matches involving the player's team)
        $unplayedMatches = $game->matches()
            ->where('played', false)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)->orWhere('away_team_id', $game->team_id))
            ->count();
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
        $competitionTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->pluck('team_id');

        // Find the cup competition for this country
        $cupCompetition = Competition::where('country', $competition->country)
            ->where('role', Competition::ROLE_DOMESTIC_CUP)
            ->first();

        $cupWinner = null;
        $cupRunnerUp = null;
        $cupName = $cupCompetition->name ?? 'Cup';

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
            ->where('role', Competition::ROLE_PRIMARY)
            ->where('tier', $competition->tier + 1)
            ->first();

        // Find the higher-tier league for relegation context (tier = current tier - 1)
        $higherTierLeague = Competition::where('country', $competition->country)
            ->where('role', Competition::ROLE_PRIMARY)
            ->where('tier', $competition->tier - 1)
            ->first();

        // Load simulated seasons for the other league(s)
        $simulatedSeasons = SimulatedSeason::with('competition')
            ->where('game_id', $gameId)
            ->where('season', $game->season)
            ->get();

        // Get promotion/relegation positions from country config
        $promotions = $this->countryConfig->promotions($competition->country);
        $promotionRule = collect($promotions)->first(fn ($r) =>
            $r['top_division'] === $competition->id || $r['bottom_division'] === $competition->id
        );
        $relegatedPositions = $promotionRule['relegated_positions'] ?? [];
        $directPromotionPositions = $promotionRule['direct_promotion_positions'] ?? [];
        $playoffPositions = $promotionRule['playoff_positions'] ?? [];

        // Relegated teams from current league
        $relegatedTeams = collect();
        $relegatedToLeague = $lowerTierLeague;
        if ($competition->tier === 1 && $lowerTierLeague && !empty($relegatedPositions)) {
            $relegatedTeams = $standings->whereIn('position', $relegatedPositions)->values();
        }

        // Promoted teams (if we're in a lower tier, show who got promoted to higher tier)
        $directlyPromoted = collect();
        $playoffWinner = null;
        $promotionTargetLeague = null;

        if ($competition->tier > 1 && $higherTierLeague) {
            // We're in a lower tier, show who got promoted
            $directlyPromoted = $standings->whereIn('position', $directPromotionPositions)->values();
            $promotionTargetLeague = $higherTierLeague;

            // Check for playoff winner in current competition
            $playoffFinal = CupTie::with('winner')
                ->where('game_id', $gameId)
                ->where('competition_id', $competition->id)
                ->where('completed', true)
                ->orderByDesc('round_number')
                ->first();

            $playoffWinner = $playoffFinal?->winner;

            // Also show relegated teams from the higher tier (from simulated data)
            $higherTierSimulated = $simulatedSeasons->firstWhere('competition_id', $higherTierLeague->id);
            if ($higherTierSimulated && !empty($relegatedPositions)) {
                $relegatedTeamIds = $higherTierSimulated->getTeamIdsAtPositions($relegatedPositions);
                $relegatedTeamModels = Team::whereIn('id', $relegatedTeamIds)->get()->keyBy('id');
                $relegatedTeams = collect();
                $relegatedToLeague = $competition; // They're relegated to our league
                foreach ($relegatedPositions as $pos) {
                    $teamId = $higherTierSimulated->results[$pos - 1] ?? null;
                    if ($teamId && $relegatedTeamModels->has($teamId)) {
                        $relegatedTeams->push((object) [
                            'team' => $relegatedTeamModels[$teamId],
                            'position' => $pos,
                        ]);
                    }
                }
            }
        } elseif ($lowerTierLeague) {
            // We're in the top tier, show who got promoted FROM the lower tier
            $lowerTierStandings = GameStanding::with('team')
                ->where('game_id', $gameId)
                ->where('competition_id', $lowerTierLeague->id)
                ->orderBy('position')
                ->get();

            if ($lowerTierStandings->isNotEmpty()) {
                // Real standings exist
                $directlyPromoted = $lowerTierStandings->whereIn('position', $directPromotionPositions)->values();
                $promotionTargetLeague = $competition;

                // Check for playoff winner in lower tier competition
                $playoffFinal = CupTie::with('winner')
                    ->where('game_id', $gameId)
                    ->where('competition_id', $lowerTierLeague->id)
                    ->where('completed', true)
                    ->orderByDesc('round_number')
                    ->first();

                $playoffWinner = $playoffFinal?->winner;
            } else {
                // Fall back to simulated results for the lower tier
                $lowerTierSimulated = $simulatedSeasons->firstWhere('competition_id', $lowerTierLeague->id);
                if ($lowerTierSimulated) {
                    // Simulated results: direct promotions + playoff winner
                    $totalPromoted = count($directPromotionPositions) + (empty($playoffPositions) ? 0 : 1);
                    $promotedSimPositions = range(1, $totalPromoted);
                    $promotedTeamIds = $lowerTierSimulated->getTeamIdsAtPositions($promotedSimPositions);
                    $promotedTeamModels = Team::whereIn('id', $promotedTeamIds)->get()->keyBy('id');
                    $promotionTargetLeague = $competition;

                    foreach ($promotedSimPositions as $pos) {
                        $teamId = $lowerTierSimulated->results[$pos - 1] ?? null;
                        if ($teamId && $promotedTeamModels->has($teamId)) {
                            $directlyPromoted->push((object) [
                                'team' => $promotedTeamModels[$teamId],
                                'position' => $pos,
                            ]);
                        }
                    }
                }
            }
        }

        // Get simulated other league champion for display
        $otherLeagueChampion = null;
        $otherLeague = null;
        foreach ($simulatedSeasons as $simulated) {
            $winnerTeamId = $simulated->getWinnerTeamId();
            if ($winnerTeamId) {
                $otherLeagueChampion = Team::find($winnerTeamId);
                $otherLeague = $simulated->competition;
                break;
            }
        }

        // Best attack and defense in the league
        $bestAttack = $standings->sortByDesc('goals_for')->first();
        $bestDefense = $standings->sortBy('goals_against')->first();

        // Manager evaluation based on season goal
        $managerEvaluation = $this->seasonGoalService->evaluatePerformance($game, $playerStanding->position ?? 20);

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

        // Players retiring at end of this season (announced last season)
        $retiringPlayers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->where('retiring_at_season', $game->season)
            ->get();

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
            'relegatedToLeague' => $relegatedToLeague,
            'bestAttack' => $bestAttack,
            'bestDefense' => $bestDefense,
            'managerEvaluation' => $managerEvaluation,
            'retiringPlayers' => $retiringPlayers,
            'otherLeagueChampion' => $otherLeagueChampion,
            'otherLeague' => $otherLeague,
        ]);
    }
}
