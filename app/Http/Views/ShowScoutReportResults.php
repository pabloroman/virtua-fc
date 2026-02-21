<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\TransferOffer;
use App\Support\PositionMapper;

class ShowScoutReportResults
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(string $gameId, string $reportId)
    {
        $game = Game::with(['team'])->findOrFail($gameId);
        $report = ScoutReport::where('game_id', $gameId)
            ->where('status', ScoutReport::STATUS_COMPLETED)
            ->findOrFail($reportId);

        $players = collect();
        $playerDetails = [];

        if (!empty($report->player_ids)) {
            $players = GamePlayer::with(['player', 'team'])
                ->whereIn('id', $report->player_ids)
                ->where('team_id', '!=', $game->team_id)
                ->get();

            // Gather scouting details and existing offer statuses for each player
            $offerStatuses = TransferOffer::getOfferStatusesForPlayers($gameId, $players->pluck('id')->toArray());

            foreach ($players as $player) {
                $detail = $this->scoutingService->getPlayerScoutingDetail($player, $game);
                $offerInfo = $offerStatuses[$player->id] ?? null;
                $detail['has_existing_offer'] = $offerInfo !== null;
                $detail['offer_status'] = $offerInfo['status'] ?? null;
                $detail['offer_is_counter'] = $offerInfo['isCounter'] ?? false;
                $detail['offer_type'] = $offerInfo['offerType'] ?? null;
                $playerDetails[$player->id] = $detail;
            }
        }

        $filters = $report->filters;
        $positionLabel = isset($filters['position'])
            ? PositionMapper::filterToDisplayName($filters['position'])
            : '-';
        $scopeLabel = isset($filters['scope']) && count($filters['scope']) === 1
            ? (in_array('domestic', $filters['scope']) ? __('transfers.scope_domestic') : __('transfers.scope_international'))
            : __('transfers.scope_domestic') . ' + ' . __('transfers.scope_international');

        $shortlistedPlayerIds = ShortlistedPlayer::where('game_id', $gameId)
            ->pluck('game_player_id')
            ->toArray();

        return view('partials.scout-report-results', [
            'game' => $game,
            'report' => $report,
            'players' => $players,
            'playerDetails' => $playerDetails,
            'positionLabel' => $positionLabel,
            'scopeLabel' => $scopeLabel,
            'isTransferWindow' => $game->isTransferWindowOpen(),
            'isPreContractPeriod' => $game->isPreContractPeriod(),
            'shortlistedPlayerIds' => $shortlistedPlayerIds,
        ]);
    }
}
