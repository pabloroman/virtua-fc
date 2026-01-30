<?php

namespace App\Http\Actions;

use App\Game\Commands\ProcessSeasonDevelopment as ProcessSeasonDevelopmentCommand;
use App\Game\Game as GameAggregate;
use App\Game\Services\PlayerDevelopmentService;
use App\Models\Game;

/**
 * Action to trigger season-end development processing.
 *
 * This should be called at the end of a season to calculate and apply
 * development changes for all players in the user's team.
 */
class ProcessSeasonDevelopment
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        // Calculate development changes for all players
        $playerChanges = $this->developmentService->processSeasonEndDevelopment(
            $gameId,
            $game->team_id
        );

        // If no changes, just redirect back
        if (empty($playerChanges)) {
            return redirect()->route('game.squad.development', $gameId)
                ->with('message', 'No development changes this season.');
        }

        // Create command and record event
        $command = new ProcessSeasonDevelopmentCommand(
            season: $game->season,
            teamId: $game->team_id,
            playerChanges: $playerChanges,
        );

        $aggregate = GameAggregate::retrieve($gameId);
        $aggregate->processSeasonDevelopment($command);

        return redirect()->route('game.squad.development', $gameId)
            ->with('message', 'Season development processed for ' . count($playerChanges) . ' players.');
    }
}
