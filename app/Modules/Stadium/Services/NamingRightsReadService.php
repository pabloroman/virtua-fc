<?php

namespace App\Modules\Stadium\Services;

use App\Models\Game;
use App\Models\GameStadium;
use App\Models\GameStadiumNamingDeal;

/**
 * Read-side for the naming-rights surfaces: the stadium-page identity panel
 * and the commercial-page offer board. Pure reads — every mutation
 * (seek/accept/rename/rollover) lives on NamingRightsService, whose query
 * surface (window / canSeek / fee / cooldown / cash) this composes into the
 * view payloads.
 */
class NamingRightsReadService
{
    public function __construct(
        private readonly NamingRightsService $namingRights,
        private readonly GameStadiumResolver $stadiumResolver,
    ) {}

    /**
     * Identity read-side for the stadium page: the current name, where it
     * comes from, and whether a cosmetic rename is allowed. The sponsorship
     * money surface lives on the Commercial page (buildCommercialPanel).
     *
     * @return array{stadiumIdentity: array<string, mixed>}
     */
    public function buildIdentityPanel(Game $game): array
    {
        $season = (int) $game->season;
        $windowOpen = $this->namingRights->windowOpen($game);
        $active = GameStadiumNamingDeal::activeForGame($game->id, $game->team_id);
        $stadium = $this->stadiumRow($game);

        return [
            'stadiumIdentity' => [
                'currentName' => $this->effectiveName($game),
                'source' => $this->nameSource($active, $stadium),
                'windowOpen' => $windowOpen,
                'canRename' => $windowOpen
                    && $active === null
                    && ! ($stadium && $stadium->name_changed_season === $season),
                'hasActiveDeal' => $active !== null,
                'sponsorName' => $active?->sponsor_name,
            ],
        ];
    }

    /**
     * Commercial read-side for the Commercial page: the active naming-rights
     * deal (if any), the pending offer board, and the proactive-search state
     * (whether the manager can seek, the agency fee, and any cooldown).
     *
     * @return array{namingRights: array<string, mixed>}
     */
    public function buildCommercialPanel(Game $game): array
    {
        $season = (int) $game->season;
        $windowOpen = $this->namingRights->windowOpen($game);
        $active = GameStadiumNamingDeal::activeForGame($game->id, $game->team_id);
        $stadium = $this->stadiumRow($game);

        $activeDeal = null;
        if ($active !== null) {
            $activeDeal = [
                'sponsor_name' => $active->sponsor_name,
                'annual_value_cents' => $active->annual_value_cents,
                'end_season' => $active->end_season,
                'seasons_remaining' => max(0, ($active->end_season ?? $season) - $season + 1),
            ];
        }

        $offers = [];
        if ($active === null) {
            $offers = GameStadiumNamingDeal::query()
                ->where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->where('status', GameStadiumNamingDeal::STATUS_PENDING)
                ->where('offered_season', $season)
                ->orderByDesc('annual_value_cents')
                ->get()
                ->map(fn (GameStadiumNamingDeal $deal) => [
                    'id' => $deal->id,
                    'sponsor_name' => $deal->sponsor_name,
                    'proposed_stadium_name' => $deal->proposed_stadium_name,
                    'annual_value_cents' => $deal->annual_value_cents,
                    'contract_seasons' => $deal->contract_seasons,
                    'is_renewal' => $deal->is_renewal,
                ])
                ->all();
        }

        $cooldownDays = $this->namingRights->seekCooldownRemainingDays($game);
        $feeCents = $this->namingRights->searchFee($game);
        $availableCashCents = $this->namingRights->availableCash($game);

        return [
            'namingRights' => [
                'currentName' => $this->effectiveName($game),
                'source' => $this->nameSource($active, $stadium),
                'windowOpen' => $windowOpen,
                'activeDeal' => $activeDeal,
                'offers' => $offers,
                'seek' => [
                    'canSeek' => $this->namingRights->canSeek($game),
                    'feeCents' => $feeCents,
                    'availableCashCents' => $availableCashCents,
                    'feeAffordable' => $feeCents <= $availableCashCents,
                    'cooldownDays' => $cooldownDays,
                    'cooldownLength' => (int) config('commercial.naming_rights.search_cooldown_days', 14),
                ],
            ],
        ];
    }

    private function effectiveName(Game $game): ?string
    {
        return $this->stadiumResolver->effectiveName(
            $game->id,
            $game->team_id,
            $game->team?->stadium_name,
        );
    }

    /**
     * Where the current name comes from: an active sponsorship, a custom
     * rename, or the historic ground name.
     */
    private function nameSource(?GameStadiumNamingDeal $active, ?GameStadium $stadium): string
    {
        return $active !== null
            ? 'sponsor'
            : (($stadium && $stadium->stadium_name !== null) ? 'custom' : 'historic');
    }

    private function stadiumRow(Game $game): ?GameStadium
    {
        return GameStadium::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();
    }
}
