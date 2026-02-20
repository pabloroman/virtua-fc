<?php

namespace App\Http\Views;

use App\Modules\Season\Jobs\SetupNewGame;
use App\Models\Competition;
use App\Models\Game;

class ShowWelcome
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // If welcome is already complete, go to onboarding or game
        if (!$game->needsWelcome()) {
            if ($game->needsOnboarding()) {
                return redirect()->route('game.onboarding', $gameId);
            }
            return redirect()->route('show-game', $gameId);
        }

        // Wait for background setup to finish
        if (!$game->isSetupComplete()) {
            if ($game->created_at->lt(now()->subMinutes(2))) {
                SetupNewGame::dispatch(
                    gameId: $game->id,
                    teamId: $game->team_id,
                    competitionId: $game->competition_id,
                    season: $game->season,
                    gameMode: $game->game_mode ?? Game::MODE_CAREER,
                );
            }
            return view('game-setup-loading', ['game' => $game]);
        }

        $competition = Competition::find($game->competition_id);

        return view('welcome-tutorial', [
            'game' => $game,
            'competition' => $competition,
        ]);
    }
}
