<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Modules\Season\Services\SeasonGoalService;

class ShowSeasonEnd
{
    public function __construct(
        private readonly SeasonGoalService $seasonGoalService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Redirect if season transition is already in progress
        if ($game->isTransitioningSeason()) {
            return redirect()->route('show-game', $gameId);
        }

        // Check if season is actually complete (only matches involving the player's team)
        $unplayedMatches = $game->matches()
            ->where('played', false)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)->orWhere('away_team_id', $game->team_id))
            ->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', 'Season is not complete yet.');
        }

        $competition = Competition::findOrFail($game->competition_id);

        // Get final standings for player's league
        $standings = GameStanding::with('team')
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->orderBy('position')
            ->get();

        $playerStanding = $standings->firstWhere('team_id', $game->team_id);
        $champion = $standings->first();

        // Standings zones for table coloring
        $standingsZones = $competition->getConfig()->getStandingsZones();

        // Team IDs in this competition (for league-wide awards)
        $competitionTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->pluck('team_id');

        // Manager evaluation
        $managerEvaluation = $this->seasonGoalService->evaluatePerformance($game, $playerStanding->position ?? 20);

        // === League Awards (sidebar) ===

        // Top scorers (Pichichi) — top 3
        $topScorers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->whereIn('team_id', $competitionTeamIds)
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->limit(3)
            ->get();

        // Best goalkeeper (Zamora) — minimum 50% appearances
        $minGoalkeeperAppearances = 19;
        $bestGoalkeeper = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->whereIn('team_id', $competitionTeamIds)
            ->where('position', 'Goalkeeper')
            ->where('appearances', '>=', $minGoalkeeperAppearances)
            ->get()
            ->sortBy(fn ($gk) => $gk->appearances > 0
                ? $gk->goals_conceded / $gk->appearances
                : 999)
            ->first();

        // === Section 2: Other Competitions ===
        $otherCompetitionResults = $this->buildOtherCompetitionResults($game);

        // === Section 3: Team in Numbers ===

        // User's team players
        $teamPlayers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get();

        $teamTopScorer = $teamPlayers->where('goals', '>', 0)->sortByDesc('goals')->first();
        $teamTopAssister = $teamPlayers->where('assists', '>', 0)->sortByDesc('assists')->first();
        $teamMostAppearances = $teamPlayers->sortByDesc('appearances')->first();
        $teamYellowCards = $teamPlayers->sum('yellow_cards');
        $teamRedCards = $teamPlayers->sum('red_cards');
        $teamCleanSheets = $teamPlayers->where('position', 'Goalkeeper')->sum('clean_sheets');

        // Retiring players from user's team
        $userTeamRetiring = $teamPlayers->where('retiring_at_season', $game->season)->values();

        // Match stats for user's team (league matches only)
        [$biggestVictory, $worstDefeat, $homeRecord, $awayRecord] = $this->buildMatchStats($game);

        // Transfer balance (income from sales minus spending on purchases)
        $transferIncome = (int) FinancialTransaction::where('game_id', $gameId)
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_IN)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');
        $transferSpend = (int) FinancialTransaction::where('game_id', $gameId)
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_OUT)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');
        $transferBalance = $transferIncome - $transferSpend;

        // === Section 4: Simulated League Results ===
        $simulatedResults = $this->buildSimulatedResults($gameId, $game->season);

        return view('season-end', [
            'game' => $game,
            'competition' => $competition,
            'standings' => $standings,
            'playerStanding' => $playerStanding,
            'champion' => $champion,
            'standingsZones' => $standingsZones,
            'managerEvaluation' => $managerEvaluation,
            // Awards sidebar
            'topScorers' => $topScorers,
            'bestGoalkeeper' => $bestGoalkeeper,
            // Other competitions
            'otherCompetitionResults' => $otherCompetitionResults,
            // Team in numbers
            'teamTopScorer' => $teamTopScorer,
            'teamTopAssister' => $teamTopAssister,
            'teamMostAppearances' => $teamMostAppearances,
            'biggestVictory' => $biggestVictory,
            'worstDefeat' => $worstDefeat,
            'homeRecord' => $homeRecord,
            'awayRecord' => $awayRecord,
            'teamYellowCards' => $teamYellowCards,
            'teamRedCards' => $teamRedCards,
            'teamCleanSheets' => $teamCleanSheets,
            'userTeamRetiring' => $userTeamRetiring,
            'transferBalance' => $transferBalance,
            // Simulated results
            'simulatedResults' => $simulatedResults,
        ]);
    }

    /**
     * Build results for each competition the user participated in (excluding the primary league).
     */
    private function buildOtherCompetitionResults(Game $game): array
    {
        $entries = CompetitionEntry::with('competition')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('competition_id', '!=', $game->competition_id)
            ->get();

        $results = [];

        foreach ($entries as $entry) {
            $comp = $entry->competition;

            // Fetch all completed ties for this competition in one query, then filter
            $allTies = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch'])
                ->where('game_id', $game->id)
                ->where('competition_id', $comp->id)
                ->where('completed', true)
                ->orderByDesc('round_number')
                ->get();

            $competitionFinal = $allTies->first();
            $wonCompetition = $competitionFinal && $competitionFinal->winner_id === $game->team_id;

            $teamTies = $allTies->filter(fn ($t) =>
                $t->home_team_id === $game->team_id || $t->away_team_id === $game->team_id
            );

            $result = [
                'competition' => $comp,
                'wonCompetition' => $wonCompetition,
                'lastTie' => null,
                'roundName' => null,
                'opponent' => null,
                'score' => null,
                'eliminated' => false,
                'swissStanding' => null,
            ];

            // Swiss-format standings (European competitions)
            if ($comp->role === Competition::ROLE_EUROPEAN) {
                $result['swissStanding'] = GameStanding::where('game_id', $game->id)
                    ->where('competition_id', $comp->id)
                    ->where('team_id', $game->team_id)
                    ->first();
            }

            if ($teamTies->isNotEmpty()) {
                $lastTie = $teamTies->first();
                $roundConfig = $lastTie->getRoundConfig();
                $won = $lastTie->winner_id === $game->team_id;
                $opponent = $lastTie->home_team_id === $game->team_id
                    ? $lastTie->awayTeam
                    : $lastTie->homeTeam;

                $result['lastTie'] = $lastTie;
                $result['roundName'] = $roundConfig?->name ?? __('season.round_n', ['n' => $lastTie->round_number]);
                $result['opponent'] = $opponent;
                $result['score'] = $lastTie->getScoreDisplay();
                $result['eliminated'] = !$won;
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Build match stats: biggest victory, worst defeat, home/away records.
     */
    private function buildMatchStats(Game $game): array
    {
        $teamMatches = GameMatch::with(['homeTeam', 'awayTeam', 'competition'])
            ->where('game_id', $game->id)
            ->where('played', true)
            ->where('competition_id', $game->competition_id)
            ->where(fn ($q) => $q
                ->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->get();

        $biggestVictory = null;
        $worstDefeat = null;
        $bestGoalDiff = 0;
        $worstGoalDiff = 0;
        $homeRecord = ['w' => 0, 'd' => 0, 'l' => 0];
        $awayRecord = ['w' => 0, 'd' => 0, 'l' => 0];

        foreach ($teamMatches as $match) {
            $isHome = $match->home_team_id === $game->team_id;
            $goalsScored = $isHome ? $match->home_score : $match->away_score;
            $goalsConceded = $isHome ? $match->away_score : $match->home_score;
            $diff = $goalsScored - $goalsConceded;

            if ($diff > $bestGoalDiff) {
                $bestGoalDiff = $diff;
                $biggestVictory = [
                    'match' => $match,
                    'opponent' => $isHome ? $match->awayTeam : $match->homeTeam,
                    'score' => $isHome
                        ? "{$match->home_score}-{$match->away_score}"
                        : "{$match->away_score}-{$match->home_score}",
                ];
            }
            if ($diff < $worstGoalDiff) {
                $worstGoalDiff = $diff;
                $worstDefeat = [
                    'match' => $match,
                    'opponent' => $isHome ? $match->awayTeam : $match->homeTeam,
                    'score' => $isHome
                        ? "{$match->home_score}-{$match->away_score}"
                        : "{$match->away_score}-{$match->home_score}",
                ];
            }

            if ($isHome) {
                if ($diff > 0) $homeRecord['w']++;
                elseif ($diff === 0) $homeRecord['d']++;
                else $homeRecord['l']++;
            } else {
                if ($diff > 0) $awayRecord['w']++;
                elseif ($diff === 0) $awayRecord['d']++;
                else $awayRecord['l']++;
            }
        }

        return [$biggestVictory, $worstDefeat, $homeRecord, $awayRecord];
    }

    /**
     * Build simulated league results for display.
     */
    private function buildSimulatedResults(string $gameId, string $season): array
    {
        $simulatedSeasons = SimulatedSeason::with('competition')
            ->where('game_id', $gameId)
            ->where('season', $season)
            ->get();

        // Collect all winner IDs first to batch-load teams
        $winnerIds = $simulatedSeasons
            ->map(fn ($s) => $s->getWinnerTeamId())
            ->filter()
            ->values()
            ->all();

        $teams = Team::whereIn('id', $winnerIds)->get()->keyBy('id');

        $results = [];
        foreach ($simulatedSeasons as $simulated) {
            $winnerTeamId = $simulated->getWinnerTeamId();
            if ($winnerTeamId && $teams->has($winnerTeamId)) {
                $results[] = [
                    'competition' => $simulated->competition,
                    'champion' => $teams[$winnerTeamId],
                ];
            }
        }

        return $results;
    }
}
