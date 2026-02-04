<?php

namespace App\Http\Actions;

use App\Game\Commands\StartNewSeason as StartNewSeasonCommand;
use App\Game\Game as GameAggregate;
use App\Game\Services\SeasonEndPipeline;
use App\Models\Game;

class StartNewSeason
{
    public function __construct(
        private readonly SeasonEndPipeline $pipeline,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Verify season is complete
        $unplayedMatches = $game->matches()->where('played', false)->count();
        if ($unplayedMatches > 0) {
            return redirect()->route('show-game', $gameId)
                ->with('error', 'Cannot start new season - current season is not complete.');
        }

        // Run the season end pipeline
        $data = $this->pipeline->run($game);

        // Record the event
        $command = new StartNewSeasonCommand(
            oldSeason: $data->oldSeason,
            newSeason: $data->newSeason,
            playerChanges: $data->playerChanges,
        );

        $aggregate = GameAggregate::retrieve($gameId);
        $aggregate->startNewSeason($command);

        return redirect()->route('game.preseason', $gameId)
            ->with('message', "Welcome to the {$data->newSeason} pre-season!");
    }
}
