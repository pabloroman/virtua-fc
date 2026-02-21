<?php

namespace App\Http\Views;

use App\Modules\Squad\Services\DevelopmentCurve;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Models\Game;
use App\Models\GamePlayer;

/**
 * View controller for squad development screen.
 *
 * Displays player potential, development status, and projections.
 */
class ShowSquadDevelopment
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        $players = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get()
            ->map(function ($player) {
                $player->setAttribute('projection', $this->developmentService->getNextSeasonProjection($player));
                $player->setAttribute('development_status', DevelopmentCurve::getStatus($player->age));
                return $player;
            })
            ->sortBy(fn ($p) => $this->sortOrder($p));

        return view('squad-development', [
            'game' => $game,
            'players' => $players,
        ]);
    }

    /**
     * Get sort order for players (by projection desc, then age asc).
     */
    private function sortOrder($player): string
    {
        $projection = 100 - ($player->projection + 50);
        $age = str_pad($player->age, 2, '0', STR_PAD_LEFT);

        return "{$projection}-{$age}";
    }
}
