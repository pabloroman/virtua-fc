<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the normalized payload rendered by <x-player-dossier-modal>, dispatched
 * via the `open-player-dossier` window event.
 *
 * The same payload shape is produced for every surface that renders
 * <x-explore-player-row> (transfer market, explore) and for scouting (search
 * results + shortlist), so a single modal renders them all.
 *
 * Base fields are always derived from the player + game. Scouting surfaces pass
 * the richer detail array from {@see \App\Modules\Transfer\Services\ScoutingService::getPlayerScoutingDetail()}
 * (optionally augmented with willingness / offer-status / shortlist keys) as
 * $scouting; its keys overlay the base, and the modal renders the extra sections
 * only when those keys are present.
 */
class PlayerDossierPresenter
{
    /**
     * @param  array<string, mixed>  $scouting  snake_case scouting detail, or [].
     * @return array<string, mixed>
     */
    public static function build(GamePlayer $player, Game $game, array $scouting = []): array
    {
        $isFreeAgent = $player->team_id === null;
        $isOnLoan = $scouting['is_on_loan'] ?? $player->is_on_loan ?? false;
        $isExpiring = !$isFreeAgent
            && $player->contract_until
            && $player->contract_until <= $game->getSeasonEndDate();

        $positionDisplay = $player->position_display;

        // Release clause folds into the negotiation (a numeric euro cap + a
        // formatted label) exactly as the inline offer button does — display
        // only; TransferService re-checks on the server when an offer meets it.
        $showsClause = ($game->release_clauses_enabled ?? false)
            && !$isFreeAgent
            && !$isOnLoan
            && $player->hasReleaseClause();

        $playerInfo = [
            'age' => $player->age($game->current_date),
            'position' => $positionDisplay['abbreviation'],
            'positionBg' => $positionDisplay['bg'],
            'positionText' => $positionDisplay['text'],
            'marketValue' => Money::format($player->market_value_cents),
            'contractYear' => $player->contract_until?->year,
        ];
        if ($showsClause) {
            $playerInfo['releaseClause'] = Money::format($player->release_clause);
            $playerInfo['releaseClauseEuros'] = (int) ($player->release_clause / 100);
        }

        $willingnessKey = $scouting['willingness_label'] ?? null;

        // Status chips for the shared <x-player-banner> (free agent / expiring /
        // scout willingness). $isExpiring already excludes free agents, so the
        // first two are mutually exclusive.
        $bannerChips = [];
        if ($isFreeAgent) {
            $bannerChips[] = ['text' => __('transfers.free_agent'), 'class' => 'bg-accent-green/10 text-accent-green'];
        }
        if ($isExpiring) {
            $bannerChips[] = ['text' => __('transfers.expiring_contract'), 'class' => 'bg-accent-gold/10 text-accent-gold'];
        }
        if ($willingnessKey) {
            $bannerChips[] = [
                'text' => __('transfers.willingness_' . $willingnessKey),
                'class' => match ($willingnessKey) {
                    'very_interested', 'open' => 'bg-accent-green/10 text-accent-green',
                    'undecided' => 'bg-accent-gold/10 text-accent-gold',
                    'reluctant', 'not_interested' => 'bg-accent-red/10 text-accent-red',
                    default => 'bg-surface-700 text-text-muted',
                },
            ];
        }

        // Nationality flag for the row's name cell — same asset path the
        // <x-explore-player-row> builds inline; null when the nationality has no
        // mapped country code.
        $flag = $player->nationality_flag;
        $nationalityFlag = ($flag['code'] ?? null) ? [
            'code' => $flag['code'],
            'name' => $flag['name'],
            'url' => Storage::disk('assets')->url('flags/' . $flag['code'] . '.svg'),
        ] : null;

        // `available_budget` is cents in the scouting detail; the action chain
        // compares euros. Fall back to the game's projected transfer budget so
        // the negotiate/loan branch still renders on non-scouting surfaces.
        $availableBudget = array_key_exists('available_budget', $scouting)
            ? (int) ($scouting['available_budget'] / 100)
            : (int) (($game->currentInvestment?->transfer_budget ?? 0) / 100);

        return [
            'id' => $player->id,
            'name' => $player->name,
            'age' => $player->age($game->current_date),
            // Header position badges still read from the payload; the banner
            // (avatar + identity + overall) is pre-rendered server-side from the
            // shared <x-player-banner> so it's identical to the owned-player detail.
            'positions' => collect($player->positions)
                ->map(fn ($pos) => PositionMapper::getPositionDisplay($pos))
                ->values()
                ->all(),
            'bannerHtml' => view('components.player-banner', [
                'game' => $game,
                'player' => $player,
                'statusChips' => $bannerChips,
            ])->render(),
            'teamName' => $player->team?->name,
            'teamImage' => $player->team?->image,
            // Exact OVR + its colour band, and the nationality flag — consumed by
            // the Alpine-rendered shortlist row (which can't call the model or the
            // <x-rating-badge> component per item).
            'ovr' => $player->effective_rating,
            'ovrClass' => RatingPalette::classFor($player->effective_rating),
            'nationalityFlag' => $nationalityFlag,
            'isFreeAgent' => $isFreeAgent,
            'isExpiring' => $isExpiring,
            'isOnLoan' => $isOnLoan,
            'marketReferenceLabel' => $player->displaysReleaseClauseAsMarketReference($game)
                ? __('transfers.release_clause')
                : __('transfers.market_value'),
            'marketReferenceValue' => $player->marketReferenceValue($game),
            'contractDate' => (!$isFreeAgent && $player->contract_until)
                ? $player->contract_until->format('M Y')
                : null,
            // Action routes — resolved here so the modal is fully self-contained.
            'negotiateUrl' => route('game.negotiate.transfer', [$game->id, $player->id]),
            'loanUrl' => route('game.negotiate.loan', [$game->id, $player->id]),
            'preContractUrl' => route('game.negotiate.pre-contract', [$game->id, $player->id]),
            'freeAgentUrl' => route('game.negotiate.free-agent', [$game->id, $player->id]),
            'playerInfo' => $playerInfo,
            // Action-gating flags. Non-scouting surfaces have no offer/cooldown
            // state, so these default to the "free to act" path.
            'isPreContractPeriod' => $game->isPreContractPeriod(),
            'hasOffer' => $scouting['has_existing_offer'] ?? false,
            'offerStatus' => $scouting['offer_status'] ?? null,
            'offerIsCounter' => $scouting['offer_is_counter'] ?? false,
            'onCooldown' => $scouting['on_cooldown'] ?? false,
            'availableBudget' => $availableBudget,
            'canAffordFee' => $scouting['can_afford_fee'] ?? true,
            'canAffordLoan' => $scouting['can_afford_loan'] ?? true,
            // Optional scouting intel — the modal renders these only when present.
            'willingnessKey' => $willingnessKey,
            'willingnessText' => $willingnessKey ? __('transfers.willingness_' . $willingnessKey) : null,
            'rivalInterest' => $scouting['rival_interest'] ?? false,
            'formattedAskingPrice' => $scouting['formatted_asking_price'] ?? null,
            'formattedWageDemand' => $scouting['formatted_wage_demand'] ?? null,
            'formattedTransferBudget' => $scouting['formatted_transfer_budget'] ?? null,
            // Shortlist removal — only shortlist cards set these; the modal shows
            // the remove control when `isShortlisted` is true.
            'isShortlisted' => $scouting['is_shortlisted'] ?? false,
            'removeUrl' => $scouting['remove_url'] ?? null,
        ];
    }
}
