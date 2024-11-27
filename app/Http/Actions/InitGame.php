<?php

namespace App\Http\Actions;

use App\Game;
use Illuminate\Http\Request;

class InitGame
{
    public function __invoke(Request $request, InitGame $initGame)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:25'],
            'team_id' => ['required'],
        ]);

        $userId = $request->user()->id;
        $playerName = $request->get('name');
        $teamId = $request->get('team_id');

        $command = new \App\CreateGame($userId, $playerName, $teamId);
        $game = Game::create($command);

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

        return redirect()->route('show-game', $game->id->toString());
    }
}
