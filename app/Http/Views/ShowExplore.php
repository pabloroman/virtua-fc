<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\ShortlistedPlayer;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Transfer\Services\ExploreService;
use App\Modules\Transfer\Services\TransferHeaderService;
use Illuminate\Http\Request;

class ShowExplore
{
    public function __construct(
        private readonly ExploreService $exploreService,
        private readonly TransferHeaderService $headerService,
        private readonly CountryConfig $countryConfig,
    ) {}

    public function __invoke(Request $request, string $gameId, ?string $slug = null)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Slug deep-link: resolve the team server-side so the page lands with
        // the correct competition + squad already pre-selected. A stray
        // ?query= on the URL is ignored — the slug wins.
        $initialTeam = null;
        $initialCompetitionId = null;
        $initialPoolId = null;
        if ($slug !== null) {
            $scope = $this->exploreService->resolveTeamScope($gameId, $slug);
            $initialTeam = $scope['team'];
            $initialCompetitionId = $scope['competitionId'];
            $initialPoolId = $scope['poolId'];
        }

        $competitions = $this->exploreService->getCompetitionsWithTeamCounts($gameId);
        $pools = $this->buildTransferPools($gameId, $game->country ?? 'ES');
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
        // Slug visits suppress search mode so the page renders the team's
        // squad in the right pane instead of a search result list.
        $searchMode = $initialTeam === null && ($hasName || $hasFilters);

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
            'pools' => $pools,
            'nationalities' => $nationalities,
            'shortlistedIds' => $shortlistedIds,
            'searchMode' => $searchMode,
            'searchResults' => $searchResults,
            'initialFilters' => $filters,
            'initialTeam' => $initialTeam,
            'initialCompetitionId' => $initialCompetitionId,
            'initialPoolId' => $initialPoolId,
            ...$this->headerService->getHeaderData($game),
        ]);
    }

    /**
     * Build the transfer-pool list (EUR / INT / future) for the explore
     * dropdown, with each pool's team count and presentation metadata.
     * Pools with zero teams are omitted so the dropdown only surfaces
     * scopes the player can actually navigate to.
     *
     * @return array<int, array{id: string, label: string, flag: string, count: int}>
     */
    private function buildTransferPools(string $gameId, string $countryCode): array
    {
        $labels = [
            'EUR' => __('transfers.explore_europe'),
            'INT' => __('transfers.explore_international'),
        ];
        // Pools render either a country flag (EUR uses the EU one) or an
        // emoji glyph when no single flag is appropriate. INT spans multiple
        // continents — the Americas-centred globe is the most recognisable
        // "rest of the world" mark.
        $flags = [
            'EUR' => 'eu',
        ];
        $emojis = [
            'INT' => '🌎',
        ];
        $hints = [
            'EUR' => __('transfers.explore_europe_hint'),
            'INT' => __('transfers.explore_international_hint'),
        ];

        // Only `team_pool`-handler entries are real pools (Europe, International).
        // The transfer_pool list also contains foreign leagues (ENG1, DEU1, …)
        // which show up under their own "Liga" group via getCompetitionsWithTeamCounts —
        // they would double-count if surfaced here as well.
        $support = $this->countryConfig->support($countryCode);
        $poolEntries = $support['transfer_pool'] ?? [];

        $pools = [];
        foreach ($poolEntries as $poolId => $poolConfig) {
            if (($poolConfig['handler'] ?? null) !== 'team_pool') {
                continue;
            }

            $count = $this->exploreService->getTeamPoolCount($gameId, $poolId);
            if ($count === 0) {
                continue;
            }

            $pools[] = [
                'id' => $poolId,
                'label' => $labels[$poolId] ?? $poolId,
                'flag' => $flags[$poolId] ?? null,
                'emoji' => $emojis[$poolId] ?? null,
                'hint' => $hints[$poolId] ?? '',
                'count' => $count,
            ];
        }

        return $pools;
    }
}
