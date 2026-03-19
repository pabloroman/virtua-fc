<?php

namespace App\Modules\Report\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Competition\Promotions\PromotionRelegationFactory;
use App\Modules\Season\Services\SeasonGoalService;

class SeasonSummaryService
{
    public function __construct(
        private readonly AwardService $awardService,
        private readonly CompetitionSummaryService $competitionSummaryService,
        private readonly SeasonGoalService $seasonGoalService,
        private readonly PromotionRelegationFactory $promotionRelegationFactory,
    ) {}

    public function buildSeasonSummary(Game $game): array
    {
        $competition = Competition::findOrFail($game->competition_id);

        $standings = GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->orderBy('position')
            ->get();

        $playerStanding = $standings->firstWhere('team_id', $game->team_id);
        $champion = $standings->first();
        $standingsZones = $competition->getConfig()->getStandingsZones();

        $competitionTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->pluck('team_id');

        $promotionData = $this->buildPromotionData($game, $competition);

        $userTeamPromoted = $promotionData
            ? collect($promotionData['promoted'])->contains('teamId', $game->team_id)
            : false;

        $managerEvaluation = $this->seasonGoalService->evaluatePerformance(
            $game,
            $playerStanding->position ?? 20,
            $userTeamPromoted
        );

        // League awards
        $topScorers = $this->awardService->getTopScorers($game->id, $competitionTeamIds, limit: 3);
        $bestGoalkeeper = $this->awardService->getTopGoalkeepers(
            $game->id, $competitionTeamIds, minAppearances: 19, limit: 1
        )->first();
        [$topMvps, $teamMvpLeader] = $this->awardService->getMvpRankings(
            $game->id, $game->competition_id, $game->team_id, limit: 3
        );

        // Other competitions
        $otherCompetitionResults = $this->competitionSummaryService->buildOtherCompetitionResults($game);

        // Team in numbers
        $teamPlayers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        $teamTopScorer = $teamPlayers->where('goals', '>', 0)->sortByDesc('goals')->first();
        $teamTopAssister = $teamPlayers->where('assists', '>', 0)->sortByDesc('assists')->first();
        $teamMostAppearances = $teamPlayers->sortByDesc('appearances')->first();
        $teamYellowCards = $teamPlayers->sum('yellow_cards');
        $teamRedCards = $teamPlayers->sum('red_cards');
        $teamCleanSheets = $teamPlayers->where('position', 'Goalkeeper')->sum('clean_sheets');

        $userTeamRetiring = $teamPlayers->where('retiring_at_season', $game->season)->values();

        [$biggestVictory, $worstDefeat, $homeRecord, $awayRecord] = $this->buildMatchStats($game);

        $transferBalance = $this->buildTransferBalance($game->id);

        $reputationData = $this->buildReputationHint($game, $playerStanding?->position);

        $simulatedResults = $this->buildSimulatedResults($game->id, $game->season);

        return [
            'competition' => $competition,
            'standings' => $standings,
            'playerStanding' => $playerStanding,
            'champion' => $champion,
            'standingsZones' => $standingsZones,
            'managerEvaluation' => $managerEvaluation,
            'topScorers' => $topScorers,
            'bestGoalkeeper' => $bestGoalkeeper,
            'topMvps' => $topMvps,
            'promotionData' => $promotionData,
            'otherCompetitionResults' => $otherCompetitionResults,
            'teamTopScorer' => $teamTopScorer,
            'teamTopAssister' => $teamTopAssister,
            'teamMostAppearances' => $teamMostAppearances,
            'teamMvpLeader' => $teamMvpLeader,
            'biggestVictory' => $biggestVictory,
            'worstDefeat' => $worstDefeat,
            'homeRecord' => $homeRecord,
            'awayRecord' => $awayRecord,
            'teamYellowCards' => $teamYellowCards,
            'teamRedCards' => $teamRedCards,
            'teamCleanSheets' => $teamCleanSheets,
            'userTeamRetiring' => $userTeamRetiring,
            'transferBalance' => $transferBalance,
            'simulatedResults' => $simulatedResults,
            'reputationData' => $reputationData,
        ];
    }

    public function buildPromotionData(Game $game, Competition $competition): ?array
    {
        $rule = $this->promotionRelegationFactory->forCompetition($competition->id);

        if (!$rule) {
            return null;
        }

        $promoted = $rule->getPromotedTeams($game);
        $relegated = $rule->getRelegatedTeams($game);

        if (empty($promoted) && empty($relegated)) {
            return null;
        }

        $teamIds = array_merge(
            array_column($promoted, 'teamId'),
            array_column($relegated, 'teamId'),
        );
        $teams = Team::whereIn('id', $teamIds)->get()->keyBy('id');

        $topLeague = Competition::find($rule->getTopDivision());
        $bottomLeague = Competition::find($rule->getBottomDivision());

        return [
            'promoted' => $promoted,
            'relegated' => $relegated,
            'teams' => $teams,
            'topLeagueName' => $topLeague ? __($topLeague->name) : '',
            'bottomLeagueName' => $bottomLeague ? __($bottomLeague->name) : '',
        ];
    }

    public function buildMatchStats(Game $game): array
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

    public function buildReputationHint(Game $game, ?int $position): array
    {
        $reputation = TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        if (!$reputation) {
            return ['level' => ClubProfile::REPUTATION_LOCAL, 'direction' => 'stable'];
        }

        $level = $reputation->reputation_level;

        if (!$position) {
            return ['level' => $level, 'direction' => 'stable'];
        }

        $competition = Competition::find($game->competition_id);
        $tier = $competition?->tier ?? 1;
        $deltas = config("reputation.position_deltas.{$tier}", config('reputation.position_deltas.1'));

        $pointsDelta = 0;
        foreach ($deltas as $maxPosition => $delta) {
            if ($position <= $maxPosition) {
                $pointsDelta = $delta;
                break;
            }
        }
        if ($pointsDelta === 0 && !empty($deltas)) {
            $pointsDelta = end($deltas);
        }

        $gravityConfig = config('reputation.gravity', []);
        $gravity = $gravityConfig[$level] ?? 0;
        $net = $pointsDelta - $gravity;

        $direction = $net > 5 ? 'rising' : ($net < -5 ? 'declining' : 'stable');

        return ['level' => $level, 'direction' => $direction];
    }

    public function buildSimulatedResults(string $gameId, string $season): array
    {
        $simulatedSeasons = SimulatedSeason::with('competition')
            ->where('game_id', $gameId)
            ->where('season', $season)
            ->get();

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

    private function buildTransferBalance(string $gameId): int
    {
        $transferIncome = (int) FinancialTransaction::where('game_id', $gameId)
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_IN)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');
        $transferSpend = (int) FinancialTransaction::where('game_id', $gameId)
            ->where('category', FinancialTransaction::CATEGORY_TRANSFER_OUT)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');

        return $transferIncome - $transferSpend;
    }
}
