<?php

namespace App\Http\Views;

use App\Models\TournamentSummary;

class ShowTournamentSummary
{
    public function __invoke(string $summaryId)
    {
        $summary = TournamentSummary::with(['team', 'competition'])->findOrFail($summaryId);

        abort_if($summary->user_id !== auth()->id(), 403);

        $data = $summary->summary_data;

        // Convert teams map arrays to stdClass for <x-team-crest> compatibility
        $teams = [];
        foreach ($data['teams'] as $id => $teamData) {
            $teams[$id] = (object) $teamData;
        }

        return view('tournament-summary', [
            'summary' => $summary,
            'competition' => $summary->competition,
            'teams' => $teams,
            'playerTeamId' => $data['player_team_id'],
            'competitionName' => $data['competition_name'],
            'championTeamId' => $data['champion_team_id'],
            'finalistTeamId' => $data['finalist_team_id'],
            'groupStandings' => $data['group_standings'],
            'knockoutTies' => $data['knockout_ties'],
            'yourMatches' => $data['your_matches'],
            'finalMatch' => $data['final_match'],
            'finalGoalEvents' => $data['final_goal_events'],
            'topScorers' => $data['top_scorers'],
            'topAssisters' => $data['top_assisters'],
            'topGoalkeepers' => $data['top_goalkeepers'],
            'topMvps' => $data['top_mvps'],
            'yourSquadStats' => $data['your_squad_stats'],
            'mvpCounts' => $data['mvp_counts'],
            'resultLabel' => $summary->result_label,
            'yourRecord' => $summary->your_record,
            'playerStanding' => $data['player_standing'],
        ]);
    }
}
