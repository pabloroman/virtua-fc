<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferHeaderService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Models\TransferOffer;
use Illuminate\Http\Request;

class ShowScoutingHub
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
        private readonly TransferHeaderService $headerService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $searchingReport = $this->scoutingService->getActiveReport($game);
        $searchHistory = $this->scoutingService->getSearchHistory($game);
        $canSearchInternationally = $this->scoutingService->canSearchInternationally($game);

        $headerData = $this->headerService->getHeaderData($game);

        // Shortlisted targets (skip any that have since become user-owned).
        $shortlistedPlayers = ShortlistedPlayer::where('game_id', $gameId)
            ->with(['gamePlayer.team', 'gamePlayer.activeLoan.parentTeam'])
            ->get()
            ->map(fn (ShortlistedPlayer $entry) => $entry->gamePlayer)
            ->filter(fn (?GamePlayer $gp) => $gp && $gp->team_id !== $game->team_id)
            ->values();

        $shortlistedPlayerIds = $shortlistedPlayers->pluck('id')->all();

        // Bulk-load offer statuses once so buildTargetData doesn't query per row.
        $existingOfferStatuses = TransferOffer::getOfferStatusesForPlayers($gameId, $shortlistedPlayerIds, $game->current_date);

        // Pre-load each target's squad once (avoids an N+1 in player importance).
        $teamIds = $shortlistedPlayers->pluck('team_id')->filter()->unique();
        $teamRosters = GamePlayer::where('game_id', $gameId)
            ->whereIn('team_id', $teamIds)
            ->get()
            ->groupBy('team_id');

        $isPreContractPeriod = $game->isPreContractPeriod();

        // Build JSON-serializable shortlist data for Alpine.js. Every target now
        // carries its full dossier — there is no per-player intel gating.
        $shortlistData = $shortlistedPlayers
            ->map(fn (GamePlayer $gp) => $this->scoutingService->buildTargetData(
                $gp,
                $game,
                $existingOfferStatuses[$gp->id] ?? null,
                $teamRosters->get($gp->team_id, collect()),
            ))
            ->all();

        return view('scouting-hub', [
            'game' => $game,
            'searchingReport' => $searchingReport,
            'searchHistory' => $searchHistory,
            // Most recent completed search — its results are surfaced inline at
            // the top of the hub (the "fresh catch" the user just searched for).
            'latestReport' => $searchHistory->first(),
            'canSearchInternationally' => $canSearchInternationally,
            'isPreContractPeriod' => $isPreContractPeriod,
            'shortlistData' => $shortlistData,
            'scoutingTier' => $game->currentInvestment?->scouting_tier ?? 0,
            ...$headerData,
        ]);
    }
}
