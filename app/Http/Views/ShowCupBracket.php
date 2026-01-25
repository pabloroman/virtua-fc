<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CupRoundTemplate;
use App\Models\CupTie;
use App\Models\Game;

class ShowCupBracket
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $competition = Competition::find('ESPCUP');

        if (!$competition) {
            abort(404, 'Copa del Rey not found');
        }

        // Get all round templates
        $rounds = CupRoundTemplate::where('competition_id', 'ESPCUP')
            ->where('season', $game->season)
            ->orderBy('round_number')
            ->get();

        // Get all ties for this game, grouped by round
        $tiesByRound = CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch', 'secondLegMatch'])
            ->where('game_id', $gameId)
            ->where('competition_id', 'ESPCUP')
            ->get()
            ->groupBy('round_number');

        // Find player's tie in current/latest round
        $playerTie = null;
        foreach ($rounds->reverse() as $round) {
            $ties = $tiesByRound->get($round->round_number, collect());
            $playerTie = $ties->first(fn ($tie) => $tie->involvesTeam($game->team_id));
            if ($playerTie) {
                break;
            }
        }

        return view('cup', [
            'game' => $game,
            'competition' => $competition,
            'rounds' => $rounds,
            'tiesByRound' => $tiesByRound,
            'playerTie' => $playerTie,
        ]);
    }
}
