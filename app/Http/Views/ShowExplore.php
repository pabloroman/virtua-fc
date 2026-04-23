<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\ShortlistedPlayer;
use App\Modules\Transfer\Services\ExploreService;
use App\Modules\Transfer\Services\TransferHeaderService;
use Illuminate\Http\Request;

class ShowExplore
{
    public function __construct(
        private readonly ExploreService $exploreService,
        private readonly TransferHeaderService $headerService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $competitions = $this->exploreService->getCompetitionsWithTeamCounts($gameId);
        $freeAgentCount = $this->exploreService->getFreeAgentCount($gameId);
        $europeTeamCount = $this->exploreService->getEuropeanTeamCount($gameId);
        $nationalities = $this->exploreService->getDistinctNationalities($gameId);

        $shortlistedIds = ShortlistedPlayer::where('game_id', $gameId)
            ->pluck('game_player_id')
            ->toArray();

        $filters = [
            'name' => trim((string) $request->query('query', '')),
            'position' => $request->query('position'),
            'min_age' => $request->query('min_age'),
            'max_age' => $request->query('max_age'),
            'nationality' => $request->query('nationality'),
            'competition_id' => $request->query('competition_id'),
            'min_value' => $request->query('min_value'),
            'max_value' => $request->query('max_value'),
            'max_contract_year' => $request->query('max_contract_year'),
            'min_overall' => $request->query('min_overall'),
            'max_overall' => $request->query('max_overall'),
        ];

        $hasName = mb_strlen($filters['name']) >= 2;
        $hasFilters = ExploreService::hasAdvancedFilters($filters);
        $searchMode = $hasName || $hasFilters;

        $searchResults = null;
        if ($searchMode) {
            $result = $this->exploreService->advancedSearch($game, $filters);
            $searchResults = [
                'players' => $result['players'],
                'query' => $filters['name'],
                'total' => $result['total'],
                'truncated' => $result['truncated'],
                'hasCriteria' => true,
            ];
        }

        return view('explore', [
            'game' => $game,
            'competitions' => $competitions,
            'freeAgentCount' => $freeAgentCount,
            'europeTeamCount' => $europeTeamCount,
            'nationalities' => $nationalities,
            'shortlistedIds' => $shortlistedIds,
            'searchMode' => $searchMode,
            'searchResults' => $searchResults,
            'initialFilters' => $filters,
            ...$this->headerService->getHeaderData($game),
        ]);
    }
}
