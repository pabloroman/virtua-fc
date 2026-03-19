<?php

namespace App\Modules\Report\Services;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use App\Models\Team;
use Illuminate\Support\Collection;

class CompetitionSummaryService
{
    public function __construct(
        private readonly AwardService $awardService,
    ) {}

    public function buildTournamentSummary(Game $game): array
    {
        $competition = Competition::find($game->competition_id);

        $groupStandings = GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->orderBy('group_label')
            ->orderBy('position')
            ->get()
            ->groupBy('group_label');

        $allMatches = GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('played', true)
            ->orderBy('scheduled_date')
            ->orderBy('round_number')
            ->get();

        $yourMatches = $allMatches->filter(fn ($m) =>
            $m->home_team_id === $game->team_id || $m->away_team_id === $game->team_id
        )->values();

        $playerStanding = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        $knockoutTies = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch'])
            ->where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->orderBy('round_number')
            ->get()
            ->groupBy('round_number');

        $finalTie = $knockoutTies->flatten()->sortByDesc('round_number')->first();
        $championTeamId = $finalTie?->winner_id;

        $finalMatch = $finalTie?->firstLegMatch;
        $finalGoalEvents = $finalMatch
            ? MatchEvent::with(['gamePlayer.player'])
                ->where('game_match_id', $finalMatch->id)
                ->whereIn('event_type', [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_OWN_GOAL])
                ->orderBy('minute')
                ->get()
            : collect();

        $championTeam = $finalTie ? Team::find($finalTie->winner_id) : null;
        $finalistTeam = $finalTie ? Team::find(
            $finalTie->winner_id === $finalTie->home_team_id
                ? $finalTie->away_team_id
                : $finalTie->home_team_id
        ) : null;

        $resultLabel = $this->computeResultLabel($knockoutTies, $game->team_id);
        $yourRecord = $this->computeTeamRecord($yourMatches, $game->team_id);

        $topScorers = $this->awardService->getTopScorers($game->id, limit: 5);
        $topAssisters = $this->awardService->getTopAssisters($game->id, limit: 5);
        $topGoalkeepers = $this->awardService->getTopGoalkeepers($game->id, minAppearances: 3, limit: 5);
        $yourSquadStats = $this->awardService->getTeamSquadStats($game->id, $game->team_id);

        [$topMvps, $teamMvpLeader, $mvpCounts] = $this->awardService->getMvpRankings(
            $game->id, $game->competition_id, $game->team_id, limit: 5
        );

        return [
            'competition' => $competition,
            'groupStandings' => $groupStandings,
            'knockoutTies' => $knockoutTies,
            'championTeamId' => $championTeamId,
            'finalMatch' => $finalMatch,
            'finalGoalEvents' => $finalGoalEvents,
            'championTeam' => $championTeam,
            'finalistTeam' => $finalistTeam,
            'resultLabel' => $resultLabel,
            'yourMatches' => $yourMatches,
            'playerStanding' => $playerStanding,
            'yourRecord' => $yourRecord,
            'topScorers' => $topScorers,
            'topAssisters' => $topAssisters,
            'topGoalkeepers' => $topGoalkeepers,
            'yourSquadStats' => $yourSquadStats,
            'topMvps' => $topMvps,
            'mvpCounts' => $mvpCounts,
        ];
    }

    public function computeResultLabel(Collection $knockoutTies, string $teamId): string
    {
        $allTies = $knockoutTies->flatten();

        if ($allTies->isEmpty()) {
            return 'group_stage';
        }

        $maxRound = $allTies->max('round_number');

        $finalTie = $allTies->where('round_number', $maxRound)->first();
        if ($finalTie && $finalTie->winner_id === $teamId) {
            return 'champion';
        }

        if ($finalTie && ($finalTie->home_team_id === $teamId || $finalTie->away_team_id === $teamId)) {
            return 'runner_up';
        }

        $teamTies = $allTies->filter(fn ($tie) =>
            $tie->home_team_id === $teamId || $tie->away_team_id === $teamId
        );

        if ($teamTies->isEmpty()) {
            return 'group_stage';
        }

        $highestRound = $teamTies->max('round_number');
        $roundsFromFinal = $maxRound - $highestRound;

        return match (true) {
            $roundsFromFinal === 0 => 'runner_up',
            $roundsFromFinal === 2 => 'semi_finalist',
            $roundsFromFinal === 3 => 'quarter_finalist',
            $roundsFromFinal === 4 => 'round_of_16',
            $roundsFromFinal === 5 => 'round_of_32',
            default => 'group_stage',
        };
    }

    public function computeTeamRecord(Collection $matches, string $teamId): array
    {
        $won = 0;
        $drawn = 0;
        $lost = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;

        foreach ($matches as $match) {
            $isHome = $match->home_team_id === $teamId;
            $scored = $isHome ? ($match->home_score ?? 0) : ($match->away_score ?? 0);
            $conceded = $isHome ? ($match->away_score ?? 0) : ($match->home_score ?? 0);

            $goalsFor += $scored;
            $goalsAgainst += $conceded;

            if ($scored > $conceded) {
                $won++;
            } elseif ($scored === $conceded) {
                $drawn++;
            } else {
                $lost++;
            }
        }

        return [
            'played' => $matches->count(),
            'won' => $won,
            'drawn' => $drawn,
            'lost' => $lost,
            'goalsFor' => $goalsFor,
            'goalsAgainst' => $goalsAgainst,
        ];
    }

    public function buildOtherCompetitionResults(Game $game): array
    {
        $entries = CompetitionEntry::with('competition')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('competition_id', '!=', $game->competition_id)
            ->get();

        $results = [];

        foreach ($entries as $entry) {
            $comp = $entry->competition;

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
}
