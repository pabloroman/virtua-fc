<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Transfer\Services\ExploreService;
use Illuminate\Http\Request;

class ExplorePlayerSearch
{
    public function __construct(
        private readonly ExploreService $exploreService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $query = trim($request->query('query', ''));

        if (mb_strlen($query) < 2) {
            return view('partials.explore-search-results', [
                'players' => collect(),
                'game' => $game,
                'query' => $query,
            ]);
        }

        $players = $this->exploreService->searchPlayersByName($game, $query);

        return view('partials.explore-search-results', [
            'players' => $players,
            'game' => $game,
            'query' => $query,
        ]);
    }
}
