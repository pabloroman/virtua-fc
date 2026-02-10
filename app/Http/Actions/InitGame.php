<?php

namespace App\Http\Actions;

use App\Game\Commands\CreateGame;
use App\Game\Game;
use App\Jobs\SendBetaFeedbackRequest;
use Illuminate\Http\Request;

class InitGame
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:25'],
            'team_id' => ['required', 'uuid'],
        ]);

        $command = new CreateGame(
            userId: (string) $request->user()->id,
            playerName: $request->get('name'),
            teamId: $request->get('team_id'),
        );

        $game = Game::create($command);

        $user = $request->user();
        if (config('beta.enabled') && ! $user->feedback_requested_at) {
            SendBetaFeedbackRequest::dispatch($user)->delay(now()->addHours(24));
        }

        return redirect()->route('game.onboarding', $game->uuid());
    }
}
