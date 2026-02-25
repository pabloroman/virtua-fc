<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use App\Models\Team;
use Illuminate\Support\Collection;

class ShowTournamentEnd
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if(!$game->isTournamentMode(), 404);

        // Check if tournament is actually complete (no unplayed matches)
        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', __('season.tournament_not_complete'));
        }

        $competition = Competition::find($game->competition_id);

        // Group standings
        $groupStandings = GameStanding::with('team')
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->orderBy('group_label')
            ->orderBy('position')
            ->get()
            ->groupBy('group_label');

        // All played matches for the tournament
        $allMatches = GameMatch::with(['homeTeam', 'awayTeam'])
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->where('played', true)
            ->orderBy('scheduled_date')
            ->orderBy('round_number')
            ->get();

        // Your team's matches
        $yourMatches = $allMatches->filter(fn ($m) =>
            $m->home_team_id === $game->team_id || $m->away_team_id === $game->team_id
        )->values();

        // Your team's group standing
        $playerStanding = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        // Knockout bracket (cup ties grouped by round)
        $knockoutTies = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch'])
            ->where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->orderBy('round_number')
            ->get()
            ->groupBy('round_number');

        // Detect champion from the final cup tie
        $finalTie = $knockoutTies->flatten()->sortByDesc('round_number')->first();
        $championTeamId = $finalTie?->winner_id;

        // Final match and goal events
        $finalMatch = $finalTie?->firstLegMatch;
        $finalGoalEvents = $finalMatch
            ? MatchEvent::with(['gamePlayer.player'])
                ->where('game_match_id', $finalMatch->id)
                ->whereIn('event_type', [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_OWN_GOAL])
                ->orderBy('minute')
                ->get()
            : collect();

        // Champion and finalist team models
        $championTeam = $finalTie ? Team::find($finalTie->winner_id) : null;
        $finalistTeam = $finalTie ? Team::find(
            $finalTie->winner_id === $finalTie->home_team_id
                ? $finalTie->away_team_id
                : $finalTie->home_team_id
        ) : null;

        // Compute result label for the player's team
        $resultLabel = $this->computeResultLabel($knockoutTies, $game->team_id);

        // Compute your team's record from matches
        $yourRecord = $this->computeTeamRecord($yourMatches, $game->team_id);

        // Tournament top scorer (Golden Boot)
        $topScorers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderBy('appearances')
            ->limit(5)
            ->get();

        // Most assists
        $topAssisters = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->where('assists', '>', 0)
            ->orderByDesc('assists')
            ->orderByDesc('goals')
            ->limit(5)
            ->get();

        // Top 5 goalkeepers (Golden Glove) - min 3 appearances for a short tournament
        $topGoalkeepers = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->where('position', 'Goalkeeper')
            ->where('appearances', '>=', 3)
            ->get()
            ->sortBy([
                ['clean_sheets', 'desc'],
                [fn ($gk) => $gk->appearances > 0 ? $gk->goals_conceded / $gk->appearances : 999, 'asc'],
            ])
            ->take(5)
            ->values();

        // Your squad stats (players who played)
        $yourSquadStats = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->orderByDesc('appearances')
            ->get();

        return view('tournament-end', [
            'game' => $game,
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
        ]);
    }

    private function computeResultLabel(Collection $knockoutTies, string $teamId): string
    {
        $allTies = $knockoutTies->flatten();

        if ($allTies->isEmpty()) {
            return 'group_stage';
        }

        $maxRound = $allTies->max('round_number');

        // Check if team won the final (highest round)
        $finalTie = $allTies->where('round_number', $maxRound)->first();
        if ($finalTie && $finalTie->winner_id === $teamId) {
            return 'champion';
        }

        // Check if team lost the final
        if ($finalTie && ($finalTie->home_team_id === $teamId || $finalTie->away_team_id === $teamId)) {
            return 'runner_up';
        }

        // Check third-place match (round before final, if it exists as a separate match)
        $thirdPlaceTie = $allTies->where('round_number', $maxRound - 1)
            ->filter(fn ($tie) => $tie->home_team_id === $teamId || $tie->away_team_id === $teamId)
            ->first();

        // Find the team's highest knockout round
        $teamTies = $allTies->filter(fn ($tie) =>
            $tie->home_team_id === $teamId || $tie->away_team_id === $teamId
        );

        if ($teamTies->isEmpty()) {
            return 'group_stage';
        }

        $highestRound = $teamTies->max('round_number');

        // Map round numbers to labels based on distance from the final
        $roundsFromFinal = $maxRound - $highestRound;

        return match (true) {
            $roundsFromFinal === 0 => 'runner_up', // Already handled above, but safety
            $roundsFromFinal === 2 => 'semi_finalist',
            $roundsFromFinal === 3 => 'quarter_finalist',
            $roundsFromFinal === 4 => 'round_of_16',
            $roundsFromFinal === 5 => 'round_of_32',
            default => 'group_stage',
        };
    }

    private function computeTeamRecord($matches, string $teamId): array
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
}
