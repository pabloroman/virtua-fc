<?php

namespace App\Http\Actions;

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

        // Set current date to the first match of the new season
        $game->refresh();
        $firstMatch = $game->getFirstCompetitiveMatch();
        if ($firstMatch) {
            $game->update(['current_date' => $firstMatch->scheduled_date]);
        }

        return redirect()->route('show-game', $gameId)
            ->with('message', __('messages.new_season_started', ['season' => Game::formatSeason($data->newSeason)]));
    }
}
