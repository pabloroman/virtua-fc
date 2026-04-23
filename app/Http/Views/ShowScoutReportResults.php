<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ScoutingService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ScoutReport;
use App\Models\ShortlistedPlayer;
use App\Models\TransferOffer;
use App\Support\PositionMapper;
use Illuminate\Support\Collection;

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

        $playerDetails = [];
        $buckets = [
            'primary' => collect(),
            'ambitious' => collect(),
            'persuasion' => collect(),
            'legacy' => collect(),
        ];

        if (!empty($report->player_ids)) {
            $players = GamePlayer::with(['player', 'team'])
                ->whereIn('id', $report->player_ids)
                ->where(fn ($q) => $q
                    ->whereNull('team_id')
                    ->orWhere('team_id', '!=', $game->team_id))
                ->get()
                ->keyBy('id');

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

                $playerDetails[$player->id] = $detail;
            }

            $buckets = $this->splitIntoBuckets($report, $players, $game->current_date);
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

        $totalResults = $buckets['primary']->count()
            + $buckets['ambitious']->count()
            + $buckets['persuasion']->count()
            + $buckets['legacy']->count();

        return view('partials.scout-report-results', [
            'game' => $game,
            'report' => $report,
            'buckets' => $buckets,
            'totalResults' => $totalResults,
            'playerDetails' => $playerDetails,
            'positionLabel' => $positionLabel,
            'scopeLabel' => $scopeLabel,
            'isTransferWindow' => $game->isTransferWindowOpen(),
            'isPreContractPeriod' => $game->isPreContractPeriod(),
            'shortlistedPlayerIds' => $shortlistedPlayerIds,
        ]);
    }

    /**
     * Split returned players into the three labelled buckets stored on the
     * report. Reports written before the three-bucket format fall back to a
     * single "legacy" bucket so pre-existing searches still render.
     *
     * @param  Collection<string, GamePlayer>  $playersById
     * @return array{primary: Collection, ambitious: Collection, persuasion: Collection, legacy: Collection}
     */
    private function splitIntoBuckets(ScoutReport $report, Collection $playersById, $currentDate): array
    {
        $filters = $report->filters ?? [];
        $primaryIds = $filters['primary_player_ids'] ?? null;
        $ambitiousIds = $filters['ambitious_player_ids'] ?? null;
        $persuasionIds = $filters['persuasion_player_ids'] ?? null;

        if ($primaryIds === null && $ambitiousIds === null && $persuasionIds === null) {
            return [
                'primary' => collect(),
                'ambitious' => collect(),
                'persuasion' => collect(),
                'legacy' => $this->orderByAbility($playersById->values()),
            ];
        }

        return [
            'primary' => $this->orderByAbility($this->pickPlayers($playersById, $primaryIds ?? [])),
            'ambitious' => $this->orderByAbility($this->pickPlayers($playersById, $ambitiousIds ?? [])),
            'persuasion' => $this->orderByAbility($this->pickPlayers($playersById, $persuasionIds ?? [])),
            'legacy' => collect(),
        ];
    }

    /**
     * @param  Collection<string, GamePlayer>  $playersById
     * @param  string[]  $ids
     * @return Collection<int, GamePlayer>
     */
    private function pickPlayers(Collection $playersById, array $ids): Collection
    {
        return collect($ids)
            ->map(fn (string $id) => $playersById->get($id))
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, GamePlayer>  $players
     * @return Collection<int, GamePlayer>
     */
    private function orderByAbility(Collection $players): Collection
    {
        return $players
            ->sortByDesc(fn (GamePlayer $p) => ($p->current_technical_ability + $p->current_physical_ability) / 2)
            ->values();
    }
}
