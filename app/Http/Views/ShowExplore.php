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

        // Shortlisted player IDs for star toggle state
        $shortlistedIds = ShortlistedPlayer::where('game_id', $gameId)
            ->pluck('game_player_id')
            ->toArray();

        return view('explore', [
            'game' => $game,
            'competitions' => $competitions,
            'freeAgentCount' => $freeAgentCount,
            'shortlistedIds' => $shortlistedIds,
            ...$this->headerService->getHeaderData($game),
        ]);
    }
}
