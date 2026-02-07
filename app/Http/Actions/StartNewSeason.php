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
                ->with('error', __('messages.season_not_complete'));
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

        return redirect()->route('show-game', $gameId)
            ->with('message', __('messages.new_season_started', ['season' => $data->newSeason]));
    }
}
