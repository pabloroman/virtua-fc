<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;

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

        // Your team's standing
        $playerStanding = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

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

        // Best goalkeeper (Golden Glove) - min 3 appearances for a short tournament
        $bestGoalkeeper = GamePlayer::with(['player', 'team'])
            ->where('game_id', $gameId)
            ->where('position', 'Goalkeeper')
            ->where('appearances', '>=', 3)
            ->get()
            ->sortBy(fn ($gk) => $gk->appearances > 0 ? $gk->goals_conceded / $gk->appearances : 999)
            ->first();

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
            'yourMatches' => $yourMatches,
            'playerStanding' => $playerStanding,
            'yourRecord' => $yourRecord,
            'topScorers' => $topScorers,
            'topAssisters' => $topAssisters,
            'bestGoalkeeper' => $bestGoalkeeper,
            'yourSquadStats' => $yourSquadStats,
        ]);
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
