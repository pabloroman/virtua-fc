<?php

namespace App\Http\Actions;

use App\Jobs\CreateGame;
use App\Jobs\InitBudgets;
use App\Jobs\InitCompetitionTeams;
use App\Jobs\InitFixtures;
use App\Jobs\InitFreeAgents;
use App\Jobs\InitSquads;
use App\Jobs\InitStandings;
use App\Services\GameManager;
use Illuminate\Http\Request;

class InitGame
{
    public function __invoke(Request $request, InitGame $initGame)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:25'],
            'team_id' => ['required', 'exists:teams,id'],
        ]);

        $userId = $request->user()->id;
        $playerName = $request->get('name');
        $teamId = $request->get('team_id');

//        $game = CreateGame::handle($userId, $playerName, $teamId);
//
//        InitCompetitionTeams::handle($game);
//        InitFreeAgents::handle($game);
//        InitSquads::handle($game);
//
//        InitFixtures::handle($game);
//        InitStandings::handle($game);
//
//        InitBudgets::handle($game);
//
//        $gameManager = new GameManager;
//        $game = $gameManager->updateGame($game)->refresh();
//
//        if ($game->nextFixture) {
//            $gameManager->generateNextLineup($game);
//        }

        return redirect()->route('game.lineup', $game);
    }
}
