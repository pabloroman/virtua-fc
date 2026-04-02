<?php

namespace App\Modules\Report\Services;

use App\Events\TournamentCompleted;
use App\Models\Game;
use App\Models\TournamentSummary;

class TournamentSnapshotService
{
    public function __construct(
        private readonly CompetitionSummaryService $competitionSummaryService,
    ) {}

    public function createSnapshot(Game $game): TournamentSummary
    {
        $data = $this->competitionSummaryService->buildTournamentSummary($game);

        $teams = $this->buildTeamsMap($data);
        $summaryData = $this->serializeSummaryData($data, $teams, $game);

        $record = $data['yourRecord'];

        $summary = TournamentSummary::create([
            'user_id' => $game->user_id,
            'team_id' => $game->team_id,
            'competition_id' => $game->competition_id,
            'result_label' => $data['resultLabel'],
            'your_record' => $record,
            'summary_data' => $summaryData,
            'tournament_date' => $game->current_date ?? now(),
            'matches_played' => ($record['won'] ?? 0) + ($record['drawn'] ?? 0) + ($record['lost'] ?? 0),
            'matches_won' => $record['won'] ?? 0,
            'matches_drawn' => $record['drawn'] ?? 0,
            'matches_lost' => $record['lost'] ?? 0,
            'goals_scored' => $record['goalsFor'] ?? 0,
            'goals_conceded' => $record['goalsAgainst'] ?? 0,
            'is_champion' => $data['resultLabel'] === 'champion',
            'result_points' => self::computeResultPoints($data['resultLabel']),
        ]);

        TournamentCompleted::dispatch($game->user_id, $summary->is_champion);

        $this->pruneOldSummaries($game->user_id);

        return $summary;
    }

    private function buildTeamsMap(array $data): array
    {
        $teams = [];

        $addTeam = function ($team) use (&$teams) {
            if ($team && !isset($teams[$team->id])) {
                $teams[$team->id] = [
                    'id' => $team->id,
                    'name' => $team->name,
                    'image' => $team->image,
                    'type' => $team->type ?? 'club',
                ];
            }
        };

        $addTeam($data['championTeam']);
        $addTeam($data['finalistTeam']);

        foreach ($data['groupStandings'] as $standings) {
            foreach ($standings as $standing) {
                $addTeam($standing->team);
            }
        }

        foreach ($data['knockoutTies'] as $ties) {
            foreach ($ties as $tie) {
                $addTeam($tie->homeTeam);
                $addTeam($tie->awayTeam);
                if ($tie->winner) {
                    $addTeam($tie->winner);
                }
            }
        }

        foreach ($data['yourMatches'] as $match) {
            $addTeam($match->homeTeam);
            $addTeam($match->awayTeam);
        }

        foreach (['topScorers', 'topAssisters', 'topGoalkeepers'] as $key) {
            foreach ($data[$key] as $gp) {
                $addTeam($gp->team);
            }
        }

        foreach ($data['topMvps'] as $mvp) {
            $addTeam($mvp->gamePlayer->team);
        }

        return $teams;
    }

    private function serializeSummaryData(array $data, array $teams, Game $game): array
    {
        $groupStandings = [];
        foreach ($data['groupStandings'] as $groupLabel => $standings) {
            $groupStandings[$groupLabel] = $standings->map(fn ($s) => [
                'team_id' => $s->team_id,
                'position' => $s->position,
                'played' => $s->played,
                'won' => $s->won,
                'drawn' => $s->drawn,
                'lost' => $s->lost,
                'goals_for' => $s->goals_for,
                'goals_against' => $s->goals_against,
                'points' => $s->points,
            ])->values()->all();
        }

        $knockoutTies = [];
        foreach ($data['knockoutTies'] as $roundNumber => $ties) {
            $roundName = null;
            $firstTie = $ties->first();
            if ($firstTie?->firstLegMatch?->round_name) {
                $roundName = $firstTie->firstLegMatch->round_name;
            }

            $knockoutTies[$roundNumber] = [
                'round_name' => $roundName,
                'ties' => $ties->map(fn ($tie) => [
                    'home_team_id' => $tie->home_team_id,
                    'away_team_id' => $tie->away_team_id,
                    'winner_id' => $tie->winner_id,
                    'home_score' => $tie->firstLegMatch?->home_score ?? 0,
                    'away_score' => $tie->firstLegMatch?->away_score ?? 0,
                    'is_extra_time' => $tie->firstLegMatch?->is_extra_time ?? false,
                    'home_score_penalties' => $tie->firstLegMatch?->home_score_penalties,
                    'away_score_penalties' => $tie->firstLegMatch?->away_score_penalties,
                ])->values()->all(),
            ];
        }

        $yourMatches = $data['yourMatches']->map(fn ($m) => [
            'home_team_id' => $m->home_team_id,
            'away_team_id' => $m->away_team_id,
            'home_score' => $m->home_score,
            'away_score' => $m->away_score,
            'is_extra_time' => $m->is_extra_time,
            'home_score_penalties' => $m->home_score_penalties,
            'away_score_penalties' => $m->away_score_penalties,
            'round_name' => $m->round_name,
            'round_number' => $m->round_number,
        ])->values()->all();

        $finalMatch = null;
        if ($data['finalMatch']) {
            $fm = $data['finalMatch'];
            $finalMatch = [
                'home_team_id' => $fm->home_team_id,
                'away_team_id' => $fm->away_team_id,
                'home_score' => $fm->home_score,
                'away_score' => $fm->away_score,
                'is_extra_time' => $fm->is_extra_time,
                'home_score_penalties' => $fm->home_score_penalties,
                'away_score_penalties' => $fm->away_score_penalties,
            ];
        }

        $finalGoalEvents = $data['finalGoalEvents']->map(function ($event) {
            $playerName = $event->gamePlayer?->player?->name ?? '?';
            $isOwnGoal = $event->event_type === \App\Models\MatchEvent::TYPE_OWN_GOAL;

            return [
                'player_name' => $playerName,
                'minute' => $event->minute,
                'is_own_goal' => $isOwnGoal,
                'team_id' => $event->team_id,
            ];
        })->values()->all();

        $serializePlayerList = fn ($collection, $fields) => $collection->map(function ($gp) use ($fields) {
            $item = ['team_id' => $gp->team_id];
            $item['player_name'] = $gp->player->name;
            foreach ($fields as $field) {
                $item[$field] = $gp->{$field};
            }
            return $item;
        })->values()->all();

        $topScorers = $serializePlayerList($data['topScorers'], ['goals', 'assists', 'appearances']);
        $topAssisters = $serializePlayerList($data['topAssisters'], ['goals', 'assists']);
        $topGoalkeepers = $serializePlayerList($data['topGoalkeepers'], ['clean_sheets', 'appearances', 'goals_conceded']);

        $topMvps = $data['topMvps']->map(fn ($mvp) => [
            'player_name' => $mvp->gamePlayer->player->name,
            'team_id' => $mvp->gamePlayer->team_id,
            'count' => $mvp->count,
        ])->values()->all();

        $yourSquadStats = $data['yourSquadStats']->map(fn ($gp) => [
            'player_id' => $gp->player_id,
            'player_name' => $gp->player->name,
            'position' => $gp->position,
            'appearances' => $gp->appearances,
            'goals' => $gp->goals,
            'assists' => $gp->assists,
            'yellow_cards' => $gp->yellow_cards,
            'red_cards' => $gp->red_cards,
            'game_player_id' => $gp->id,
        ])->values()->all();

        $mvpCounts = [];
        foreach ($data['mvpCounts'] as $gpId => $count) {
            $mvpCounts[$gpId] = $count;
        }

        return [
            'teams' => $teams,
            'player_team_id' => $game->team_id,
            'competition_name' => $data['competition']?->name,
            'champion_team_id' => $data['championTeamId'],
            'finalist_team_id' => $data['finalistTeam']?->id,
            'group_standings' => $groupStandings,
            'knockout_ties' => $knockoutTies,
            'your_matches' => $yourMatches,
            'final_match' => $finalMatch,
            'final_goal_events' => $finalGoalEvents,
            'top_scorers' => $topScorers,
            'top_assisters' => $topAssisters,
            'top_goalkeepers' => $topGoalkeepers,
            'top_mvps' => $topMvps,
            'your_squad_stats' => $yourSquadStats,
            'mvp_counts' => $mvpCounts,
            'player_standing' => $data['playerStanding'] ? [
                'position' => $data['playerStanding']->position,
                'group_label' => $data['playerStanding']->group_label,
            ] : null,
        ];
    }

    public static function computeResultPoints(string $resultLabel): int
    {
        return match ($resultLabel) {
            'champion' => 7,
            'runner_up' => 6,
            'third_place' => 5,
            'semi_finalist' => 4,
            'quarter_finalist' => 3,
            'round_of_16' => 2,
            default => 1,
        };
    }

    public static function resultLabelFromPoints(int $points): string
    {
        return match ($points) {
            7 => 'champion',
            6 => 'runner_up',
            5 => 'third_place',
            4 => 'semi_finalist',
            3 => 'quarter_finalist',
            2 => 'round_of_16',
            default => 'group_stage',
        };
    }

    private function pruneOldSummaries(string $userId): void
    {
        $count = TournamentSummary::where('user_id', $userId)->count();

        if ($count > 20) {
            $idsToDelete = TournamentSummary::where('user_id', $userId)
                ->orderBy('created_at')
                ->limit($count - 20)
                ->pluck('id');

            TournamentSummary::whereIn('id', $idsToDelete)->delete();
        }
    }
}
