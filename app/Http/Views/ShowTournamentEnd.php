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

        // Load champion and finalist teams
        $championTeam = $championTeamId ? Team::find($championTeamId) : null;
        $finalistTeamId = $finalTie ? $finalTie->getLoserId() : null;
        $finalistTeam = $finalistTeamId ? Team::find($finalistTeamId) : null;

        // Load final match with goal events
        $finalMatch = $finalTie?->firstLegMatch;
        $finalGoalEvents = $finalMatch
            ? MatchEvent::with(['gamePlayer.player', 'team'])
                ->where('game_match_id', $finalMatch->id)
                ->whereIn('event_type', [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_OWN_GOAL])
                ->orderBy('minute')
                ->get()
            : collect();

        // Compute your team's record from matches
        $yourRecord = $this->computeTeamRecord($yourMatches, $game->team_id);

        // Determine player's finish label
        $finishLabel = $this->computeFinishLabel($knockoutTies, $game->team_id, $championTeamId);

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
            ->sortBy(fn ($gk) => $gk->appearances > 0 ? $gk->goals_conceded / $gk->appearances : 999)
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
            'championTeam' => $championTeam,
            'finalistTeam' => $finalistTeam,
            'finalTie' => $finalTie,
            'finalMatch' => $finalMatch,
            'finalGoalEvents' => $finalGoalEvents,
            'yourMatches' => $yourMatches,
            'playerStanding' => $playerStanding,
            'yourRecord' => $yourRecord,
            'finishLabel' => $finishLabel,
            'topScorers' => $topScorers,
            'topAssisters' => $topAssisters,
            'topGoalkeepers' => $topGoalkeepers,
            'yourSquadStats' => $yourSquadStats,
        ]);
    }

    private function computeFinishLabel($knockoutTies, string $teamId, ?string $championTeamId): string
    {
        if ($championTeamId === $teamId) {
            return 'season.finish_champion';
        }

        $allTies = $knockoutTies->flatten();
        $teamTies = $allTies->filter(fn ($tie) => $tie->involvesTeam($teamId));

        if ($teamTies->isEmpty()) {
            return 'season.finish_group_stage';
        }

        $maxRound = $allTies->max('round_number');
        $bestRound = $teamTies->max('round_number');
        $roundsFromFinal = $maxRound - $bestRound;

        return match ($roundsFromFinal) {
            0 => 'season.finish_finalist',
            1 => 'season.finish_semi_finalist',
            2 => 'season.finish_quarter_finalist',
            3 => 'season.finish_round_of_16',
            default => 'season.finish_group_stage',
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
