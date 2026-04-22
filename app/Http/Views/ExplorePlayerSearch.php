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

        $filters = [
            'name' => trim($request->query('query', '')),
            'position' => $request->query('position'),
            'min_age' => $request->query('min_age'),
            'max_age' => $request->query('max_age'),
            'nationality' => $request->query('nationality'),
            'competition_id' => $request->query('competition_id'),
            'team_id' => $request->query('team_id'),
            'min_value' => $request->query('min_value'),
            'max_value' => $request->query('max_value'),
            'max_contract_year' => $request->query('max_contract_year'),
        ];

        $hasName = mb_strlen($filters['name']) >= 2;
        $hasFilters = ExploreService::hasAdvancedFilters($filters);

        // Nothing to search on — return an empty panel so the Alpine caller can
        // decide whether to show the "enter a name or set a filter" hint.
        if (!$hasName && !$hasFilters) {
            return view('partials.explore-search-results', [
                'players' => collect(),
                'game' => $game,
                'query' => $filters['name'],
                'total' => 0,
                'truncated' => false,
                'hasCriteria' => false,
            ]);
        }

        $result = $this->exploreService->advancedSearch($game, $filters);

        return view('partials.explore-search-results', [
            'players' => $result['players'],
            'game' => $game,
            'query' => $filters['name'],
            'total' => $result['total'],
            'truncated' => $result['truncated'],
            'hasCriteria' => true,
        ]);
    }
}
