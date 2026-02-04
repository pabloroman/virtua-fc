<?php

namespace App\Http\Actions;

use App\Game\Commands\ConductCupDraw as ConductCupDrawCommand;
use App\Game\Game as GameAggregate;
use App\Game\Services\CupDrawService;
use App\Models\Game;

class ConductCupDraw
{
    public function __construct(
        private readonly CupDrawService $cupDrawService,
    ) {}

    public function __invoke(string $gameId, int $round)
    {
        $game = Game::findOrFail($gameId);
        $competitionId = 'ESPCUP';

        // Check if draw is needed
        if (!$this->cupDrawService->needsDrawForRound($gameId, $competitionId, $round)) {
            return redirect()->route('game.competition', [$gameId, $competitionId])
                ->with('error', 'Draw not needed for this round');
        }

        // Conduct the draw
        $ties = $this->cupDrawService->conductDraw($gameId, $competitionId, $round);

        // Record the event
        $command = new ConductCupDrawCommand(
            competitionId: $competitionId,
            roundNumber: $round,
        );

        $aggregate = GameAggregate::retrieve($gameId);
        $aggregate->conductCupDraw($command, $ties->pluck('id')->toArray());

        return redirect()->route('game.competition', [$gameId, $competitionId])
            ->with('message', "Round {$round} draw conducted: {$ties->count()} ties created");
    }
}
