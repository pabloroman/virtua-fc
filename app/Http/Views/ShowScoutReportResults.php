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
        $stretchIds = $report->filters['stretch_player_ids'] ?? [];

        if (!empty($report->player_ids)) {
            $players = GamePlayer::with(['player', 'team'])
                ->whereIn('id', $report->player_ids)
                ->where(fn ($q) => $q
                    ->whereNull('team_id')
                    ->orWhere('team_id', '!=', $game->team_id))
                ->get();

            // Pre-load rosters for every candidate's team so importance /
            // willingness lookups below don't fire one query per player.
            $candidateTeamIds = $players->pluck('team_id')->filter()->unique();
            $teamRosters = GamePlayer::where('game_id', $gameId)
                ->whereIn('team_id', $candidateTeamIds)
                ->get()
                ->groupBy('team_id');

            // Gather scouting details, offer statuses, and the scout's own
            // willingness read-out for each player. Willingness is surfaced on
            // scout reports directly (even without deep-intel tracking) because
            // the scout specifically filtered by it — exposing the reason is
            // consistent with the pitch.
            $offerStatuses = TransferOffer::getOfferStatusesForPlayers($gameId, $players->pluck('id')->toArray(), $game->current_date);

            foreach ($players as $player) {
                $detail = $this->scoutingService->getPlayerScoutingDetail($player, $game);
                $offerInfo = $offerStatuses[$player->id] ?? null;
                $detail['has_existing_offer'] = $offerInfo !== null && $offerInfo['status'] !== null;
                $detail['offer_status'] = $offerInfo['status'] ?? null;
                $detail['offer_is_counter'] = $offerInfo['isCounter'] ?? false;
                $detail['offer_type'] = $offerInfo['offerType'] ?? null;
                $detail['on_cooldown'] = $offerInfo['onCooldown'] ?? false;

                $teammates = $teamRosters->get($player->team_id, collect());
                $importance = $this->scoutingService->calculatePlayerImportance($player, $teammates);
                $willingness = $this->scoutingService->calculateWillingness($player, $game, $importance);
                $detail['willingness_label'] = $willingness['label'];
                $detail['is_stretch_target'] = in_array($player->id, $stretchIds, true);

                $playerDetails[$player->id] = $detail;
            }

            // Order: willing candidates first (by willingness desc), then stretch
            // targets at the bottom. Keeps the "who you can actually sign" pitch
            // visible at the top of the list.
            $players = $players->sortBy(function (GamePlayer $p) use ($playerDetails) {
                $detail = $playerDetails[$p->id];
                // Stretch targets sink to the bottom; within each bucket, sort
                // by overall ability so the highest-quality names lead.
                $stretchRank = $detail['is_stretch_target'] ? 1 : 0;
                $ability = ($p->current_technical_ability + $p->current_physical_ability) / 2;
                return sprintf('%d_%05d', $stretchRank, 999 - (int) $ability);
            })->values();
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
