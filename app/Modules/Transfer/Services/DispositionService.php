<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Transfer\Enums\NegotiationScenario;
use Illuminate\Support\Collection;

class DispositionService
{
    // =========================================
    // CONSTANTS
    // =========================================

    /**
     * Acceptance probability modifiers based on reputation gap (source - offering).
     * Gap ≤ 0 means moving up or lateral → no penalty.
     */
    private const REPUTATION_GAP_MODIFIERS = [
        0 => 1.00,
        1 => 0.75,
        2 => 0.45,
        3 => 0.20,
        4 => 0.08,
        5 => 0.02,
    ];

    /**
     * Minimum team reputation required for a free agent to accept, based on player tier.
     * Higher-tier free agents demand higher-reputation clubs.
     */
    private const MIN_REPUTATION_BY_PLAYER_TIER = [
        5 => ClubProfile::REPUTATION_CONTINENTAL,  // €50M+ World Class → need continental+
        4 => ClubProfile::REPUTATION_ESTABLISHED,   // €20M+ Excellent → need established+
        3 => ClubProfile::REPUTATION_MODEST,         // €5M+ Good → need modest+
        2 => ClubProfile::REPUTATION_LOCAL,           // €1M+ Average → any team
        1 => ClubProfile::REPUTATION_LOCAL,           // <€1M Developing → any team
    ];

    /** Default modifier for gaps of 5+. */
    private const REPUTATION_GAP_MAX_MODIFIER = 0.02;

    private const AMBITION_PENALTY_PER_TIER_GAP = 0.12;

    /**
     * Minimum years remaining on a contract before a content player shows
     * reluctance to renegotiate. Players with fewer years left always engage.
     */
    public const RENEWAL_LONG_CONTRACT_YEARS = 3;

    /**
     * Morale threshold above which a player on a long contract is considered
     * content enough to decline renegotiation talks. Below this, the player
     * will always entertain talks (wage bump opportunity).
     */
    public const RENEWAL_CONTENT_MORALE_THRESHOLD = 60;

    // =========================================
    // PLAYER IMPORTANCE
    // =========================================

    /**
     * Calculate player importance within their team (0.0 to 1.0).
     *
     * @param GamePlayer $player
     * @param Collection|null $teammates Pre-loaded teammates to avoid repeated queries
     */
    public function playerImportance(GamePlayer $player, ?Collection $teammates = null): float
    {
        // Free agents have no team context — return neutral importance
        if ($player->team_id === null) {
            return 0.0;
        }

        if ($teammates === null) {
            $teammates = GamePlayer::where('game_id', $player->game_id)
                ->where('team_id', $player->team_id)
                ->get();
        }

        if ($teammates->isEmpty()) {
            return 0.5;
        }

        // Rank by overall ability (technical + physical average)
        $sorted = $teammates->sortByDesc(function ($p) {
            return ($p->current_technical_ability + $p->current_physical_ability) / 2;
        })->values();

        $rank = $sorted->search(fn ($p) => $p->id === $player->id);

        if ($rank === false) {
            return 0.5;
        }

        // Convert rank to 0.0-1.0 scale (0 = worst, 1 = best)
        $total = $sorted->count();

        return 1.0 - ($rank / max($total - 1, 1));
    }

    // =========================================
    // REPUTATION MODIFIER
    // =========================================

    /**
     * Calculate the acceptance probability modifier based on reputation gap.
     * Compares the player's current team reputation to the bidding team's reputation.
     *
     * @return float Modifier between 0.02 and 1.0
     */
    public function reputationModifier(Team $biddingTeam, GamePlayer $player): float
    {
        // Free agents have no current team context — no penalty
        if ($player->team_id === null) {
            return 1.0;
        }

        $gameId = $player->game_id;
        $sourceReputation = $gameId && $player->team_id
            ? TeamReputation::resolveLevel($gameId, $player->team_id)
            : ($player->team?->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_LOCAL);
        $offeringReputation = $gameId
            ? TeamReputation::resolveLevel($gameId, $biddingTeam->id)
            : ($biddingTeam->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_LOCAL);

        $sourceIndex = ClubProfile::getReputationTierIndex($sourceReputation);
        $offeringIndex = ClubProfile::getReputationTierIndex($offeringReputation);

        $gap = $sourceIndex - $offeringIndex;

        if ($gap <= 0) {
            return 1.0; // Moving up or lateral
        }

        return self::REPUTATION_GAP_MODIFIERS[$gap] ?? self::REPUTATION_GAP_MAX_MODIFIER;
    }

    // =========================================
    // UNIFIED NEGOTIATION DISPOSITION
    // =========================================

    /**
     * Calculate a player's disposition for any negotiation scenario.
     *
     * Disposition (0.10-0.95) represents how willing a player is to accept
     * a lower wage. Higher = more flexible. All scenarios share the same
     * factor-based algorithm with scenario-driven weights.
     *
     * @param GamePlayer $player The player being negotiated with
     * @param NegotiationScenario $scenario The type of negotiation
     * @param Game|null $buyingClubGame The buying club's game (null for renewals)
     * @param int $round Current negotiation round (1-3)
     */
    public function calculateNegotiationDisposition(
        GamePlayer $player,
        NegotiationScenario $scenario,
        ?Game $buyingClubGame = null,
        int $round = 1,
    ): float {
        $disposition = $scenario->baseDisposition();
        $age = $player->age($player->game->current_date);
        $isRenewal = $scenario === NegotiationScenario::RENEWAL;

        // ── Morale ──
        $disposition += $this->moraleFactor($player->morale, $isRenewal);

        // ── Appearances (renewal only: reward loyalty / penalize bench warming) ──
        if ($isRenewal) {
            $disposition += $this->appearancesFactor($player);
        }

        // ── Age ──
        $disposition += $this->ageFactor($age, $isRenewal);

        // ── Round penalty (all scenarios) ──
        $disposition += $this->roundPenalty($round);

        // ── Pre-contract pressure (renewal only: expiring players are harder to renew) ──
        if ($isRenewal) {
            $disposition += $this->preContractPressureFactor($player);
        }

        // ── Reputation step bonus (transfer/free-agent: moving up/down matters) ──
        if (in_array($scenario, [NegotiationScenario::TRANSFER, NegotiationScenario::FREE_AGENT])
            && $buyingClubGame?->team_id && $player->team_id) {
            $disposition += $this->reputationStepBonus($player, $buyingClubGame);
        }

        // ── Ambition: top-tier players resist joining clubs below their level ──
        $targetTeamId = $isRenewal ? $player->team_id : $buyingClubGame?->team_id;
        if ($targetTeamId) {
            $disposition += $this->ambitionPenalty($player, $targetTeamId, $isRenewal);
        }

        // ── Pre-contract: reputation modifier applied multiplicatively ──
        if ($scenario === NegotiationScenario::PRE_CONTRACT && $buyingClubGame?->team) {
            $disposition *= $this->reputationModifier($buyingClubGame->team, $player);
        }

        return max(0.10, min(0.95, $disposition));
    }

    /**
     * Decide whether a player is willing to sit down for contract renewal talks at all.
     *
     * Gates the opening of the negotiation before any wage demand is computed.
     * A content player (morale ≥ threshold) with plenty of contract left has no
     * incentive to renegotiate. An unhappy player, or one nearing the end of
     * their deal, will always engage.
     */
    public function isWillingToNegotiateRenewal(GamePlayer $player): bool
    {
        if (!$player->contract_until) {
            return true;
        }

        $yearsRemaining = $player->game->current_date->diffInYears($player->contract_until);

        if ($yearsRemaining < self::RENEWAL_LONG_CONTRACT_YEARS) {
            return true;
        }

        return $player->morale < self::RENEWAL_CONTENT_MORALE_THRESHOLD;
    }

    // ── Disposition factor helpers ──

    private function moraleFactor(int $morale, bool $isRenewal): float
    {
        if ($morale >= ($isRenewal ? 80 : 70)) {
            return $isRenewal ? 0.15 : 0.10;
        }
        if ($isRenewal && $morale >= 60) {
            return 0.08;
        }
        if ($morale < 40) {
            return $isRenewal ? -0.10 : -0.05;
        }

        return 0.0;
    }

    private function appearancesFactor(GamePlayer $player): float
    {
        $appearances = $player->season_appearances ?? $player->appearances ?? 0;

        if ($appearances >= 25) {
            return 0.10;
        }
        if ($appearances >= 15) {
            return 0.05;
        }
        if ($appearances < 10) {
            return -0.10;
        }

        return 0.0;
    }

    private function ageFactor(int $age, bool $isRenewal): float
    {
        if ($age >= PlayerAge::PRIME_END) {
            return $isRenewal ? 0.12 : 0.10;
        }
        if ($isRenewal && $age >= PlayerAge::primePhaseAge(0.5)) {
            return 0.05;
        }
        if ($age <= PlayerAge::YOUNG_END) {
            return $isRenewal ? -0.08 : -0.05;
        }

        return 0.0;
    }

    private function roundPenalty(int $round): float
    {
        if ($round >= 3) {
            return -0.10;
        }
        if ($round === 2) {
            return -0.05;
        }

        return 0.0;
    }

    private function preContractPressureFactor(GamePlayer $player): float
    {
        $month = $player->game->current_date->month;

        if ($month < 1 || $month > 5) {
            return 0.0;
        }

        if ($player->relationLoaded('transferOffers')) {
            $hasPreContractOffer = $player->transferOffers->contains(function ($offer) {
                return $offer->offer_type === TransferOffer::TYPE_PRE_CONTRACT
                    && $offer->status === TransferOffer::STATUS_PENDING;
            });
        } else {
            $hasPreContractOffer = $player->transferOffers()
                ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                ->where('status', TransferOffer::STATUS_PENDING)
                ->exists();
        }

        return $hasPreContractOffer ? -0.15 : -0.08;
    }

    private function reputationStepBonus(GamePlayer $player, Game $buyingClubGame): float
    {
        $gameId = $player->game_id;
        $sourceIndex = ClubProfile::getReputationTierIndex(
            TeamReputation::resolveLevel($gameId, $player->team_id)
        );
        $offeringIndex = ClubProfile::getReputationTierIndex(
            TeamReputation::resolveLevel($gameId, $buyingClubGame->team_id)
        );
        $gap = $offeringIndex - $sourceIndex;

        return match (true) {
            $gap >= 2 => 0.15,
            $gap === 1 => 0.10,
            $gap === 0 => 0.05,
            $gap === -1 => -0.05,
            default => -0.15,
        };
    }

    private function ambitionPenalty(GamePlayer $player, string $targetTeamId, bool $isRenewal): float
    {
        $reputationLevel = TeamReputation::resolveLevel($player->game_id, $targetTeamId);
        $clubReputationIndex = ClubProfile::getReputationTierIndex($reputationLevel);
        $playerTierIndex = ($player->tier ?? 1) - 1;

        $tierGap = $playerTierIndex - $clubReputationIndex;

        if ($tierGap <= 0) {
            return 0.0;
        }

        $penaltyPerTier = $isRenewal ? self::AMBITION_PENALTY_PER_TIER_GAP : 0.15;

        return -$tierGap * $penaltyPerTier;
    }

    // =========================================
    // CLUB DISPOSITION (WILLINGNESS TO SELL)
    // =========================================

    /**
     * Calculate selling club's disposition (willingness to sell).
     * Higher = more willing.
     */
    public function clubSellDisposition(GamePlayer $player): float
    {
        $disposition = 0.50;

        // Player importance (key players are harder to buy)
        $importance = $this->playerImportance($player);
        if ($importance >= 0.85) {
            $disposition -= 0.20;
        } elseif ($importance >= 0.60) {
            $disposition -= 0.10;
        } elseif ($importance <= 0.30) {
            $disposition += 0.10;
        }

        // Contract length (longer = more reluctant)
        if ($player->contract_until) {
            $yearsLeft = $player->game->current_date->diffInYears($player->contract_until);
            if ($yearsLeft >= 4) {
                $disposition -= 0.10;
            } elseif ($yearsLeft <= 1) {
                $disposition += 0.15;
            }
        } else {
            $disposition += 0.20; // No contract = very willing
        }

        // Transfer listed = very willing
        if ($player->isTransferListed()) {
            $disposition += 0.20;
        }

        // Age (older = more willing to sell)
        $age = $player->age($player->game->current_date);
        if ($age >= PlayerAge::PRIME_END) {
            $disposition += 0.10;
        } elseif ($age < PlayerAge::YOUNG_END) {
            $disposition -= 0.05;
        }

        return max(0.10, min(0.95, $disposition));
    }

    // =========================================
    // PLAYER TRANSFER WILLINGNESS (0-100 SCORE)
    // =========================================

    /**
     * Calculate a player's willingness to transfer (0-100 score mapped to label).
     *
     * @return array{score: int, label: string}
     */
    public function playerTransferWillingness(GamePlayer $player, Game $game, ?float $importance = null): array
    {
        $importance ??= $this->playerImportance($player);

        // Base willingness: low importance players are more willing
        $score = (int) ((1.0 - $importance) * 50);

        // Contract length factor: fewer years left = more willing
        if ($player->contract_until) {
            $yearsLeft = max(0, $game->current_date->diffInYears($player->contract_until));
            if ($yearsLeft <= 1) {
                $score += 30;
            } elseif ($yearsLeft <= 2) {
                $score += 15;
            }
        } else {
            $score += 25; // No contract = very willing
        }

        // Age factor: older players at lower-rep clubs more open
        $age = $player->age($game->current_date);
        if ($age >= PlayerAge::PRIME_END) {
            $score += 10;
        } elseif ($age < PlayerAge::YOUNG_END) {
            $score += 5; // Young players seeking opportunities
        }

        // Reputation gap: penalize moving down, reward moving up
        $reputationModifier = $this->reputationModifier($game->team, $player);
        if ($reputationModifier < 1.0) {
            // Moving down: scale the score down proportionally to the reputation gap
            $score = (int) ($score * $reputationModifier);
        } elseif ($player->team_id) {
            // Moving up: bonus based on how many tiers above the buying club is
            $sourceReputation = TeamReputation::resolveLevel($player->game_id, $player->team_id);
            $offeringReputation = TeamReputation::resolveLevel($player->game_id, $game->team_id);
            $sourceIndex = ClubProfile::getReputationTierIndex($sourceReputation);
            $offeringIndex = ClubProfile::getReputationTierIndex($offeringReputation);
            $upwardGap = $offeringIndex - $sourceIndex;

            if ($upwardGap >= 3) {
                $score += 30; // Dream move (e.g. local → elite)
            } elseif ($upwardGap === 2) {
                $score += 20; // Big step up
            } elseif ($upwardGap === 1) {
                $score += 10; // Step up
            }
        }

        $score = min(100, max(0, $score + rand(-5, 5)));

        $label = match (true) {
            $score >= 80 => 'very_interested',
            $score >= 60 => 'open',
            $score >= 40 => 'undecided',
            $score >= 20 => 'reluctant',
            default => 'not_interested',
        };

        return ['score' => $score, 'label' => $label];
    }

    // =========================================
    // FREE AGENT WILLINGNESS
    // =========================================

    /**
     * Check whether a free agent is willing to sign for a given team,
     * based on the player's tier vs the team's reputation.
     */
    public function canSignFreeAgent(GamePlayer $player, string $gameId, string $teamId): bool
    {
        return $this->freeAgentWillingnessLevel($player, $gameId, $teamId) === 'willing';
    }

    /**
     * Check whether a player is willing to accept a pre-contract offer from a team,
     * based on the player's tier vs the bidding team's reputation.
     *
     * Uses the same tier-vs-reputation floor as free-agent signings: an expiring
     * player moving on a free transfer is close enough to a free agent that the
     * same ambition gate applies. Prevents e.g. a tier-5 star from accepting a
     * pre-contract with a Segunda-level club regardless of wage offered.
     */
    public function canSignPreContract(GamePlayer $player, string $gameId, string $teamId): bool
    {
        return $this->meetsTierReputationFloor($player, $gameId, $teamId);
    }

    /**
     * Determine a free agent's willingness to sign for a team.
     *
     * @return string 'willing' (will sign), 'reluctant' (1 tier below minimum), or 'unwilling' (2+ below)
     */
    public function freeAgentWillingnessLevel(GamePlayer $player, string $gameId, string $teamId): string
    {
        $gap = $this->tierReputationGap($player, $gameId, $teamId);

        if ($gap <= 0) {
            return 'willing';
        }

        if ($gap === 1) {
            return 'reluctant';
        }

        return 'unwilling';
    }

    /**
     * Whether the team's reputation meets the minimum required by the player's tier.
     */
    private function meetsTierReputationFloor(GamePlayer $player, string $gameId, string $teamId): bool
    {
        return $this->tierReputationGap($player, $gameId, $teamId) <= 0;
    }

    /**
     * How far the team's reputation sits below the player-tier minimum.
     * Positive = team is below the floor; 0 or negative = team meets or exceeds it.
     */
    private function tierReputationGap(GamePlayer $player, string $gameId, string $teamId): int
    {
        $playerTier = $player->tier ?? PlayerTierService::tierFromMarketValue($player->market_value_cents);
        $minReputation = self::MIN_REPUTATION_BY_PLAYER_TIER[$playerTier] ?? ClubProfile::REPUTATION_LOCAL;

        $teamReputation = TeamReputation::resolveLevel($gameId, $teamId);

        $teamIndex = ClubProfile::getReputationTierIndex($teamReputation);
        $minIndex = ClubProfile::getReputationTierIndex($minReputation);

        return $minIndex - $teamIndex;
    }

    // =========================================
    // LOAN EVALUATION
    // =========================================

    /**
     * Evaluate a loan request from the user.
     *
     * @return array{result: string, message: string}
     */
    public function evaluateLoanRequest(GamePlayer $player, ?Game $game = null): array
    {
        // Reputation gate: player may refuse to join a lower-reputation club
        if ($game) {
            $reputationModifier = $this->reputationModifier($game->team, $player);
            if ($reputationModifier < 1.0 && rand(1, 100) > (int) ($reputationModifier * 100)) {
                return [
                    'result' => 'rejected',
                    'message' => __('transfers.loan_rejected_not_interested', ['player' => $player->name]),
                ];
            }
        }

        $importance = $this->playerImportance($player);

        // If the club has already publicly signalled willingness to part with the player
        // (listed for sale or actively loan-searched), skip the importance-based rejection
        // gates. Rejecting as "key player" would contradict the club's own market stance —
        // see clubSellDisposition() which already applies the same signal to sell willingness.
        if ($player->isTransferListed() || $player->hasActiveLoanSearch()) {
            return [
                'result' => 'accepted',
                'message' => __('transfers.loan_accepted', ['team' => $player->team?->name, 'player' => $player->name]),
            ];
        }

        if ($importance > 0.7) {
            return [
                'result' => 'rejected',
                'message' => __('transfers.loan_rejected_key_player', ['team' => $player->team?->name, 'player' => $player->name]),
            ];
        }

        if ($importance > 0.4) {
            // 50% chance
            if (rand(0, 1) === 1) {
                return [
                    'result' => 'accepted',
                    'message' => __('transfers.loan_accepted', ['team' => $player->team?->name, 'player' => $player->name]),
                ];
            }

            return [
                'result' => 'rejected',
                'message' => __('transfers.loan_rejected_keep', ['team' => $player->team?->name, 'player' => $player->name]),
            ];
        }

        return [
            'result' => 'accepted',
            'message' => __('transfers.loan_accepted', ['team' => $player->team?->name, 'player' => $player->name]),
        ];
    }

    /**
     * Deterministic loan request evaluation for sync negotiation.
     * Returns result, asking loan fee, mood, and rejection reason.
     *
     * @return array{result: string, disposition: float, rejection_reason: ?string}
     */
    public function evaluateLoanRequestSync(GamePlayer $player, Game $game): array
    {
        // Gate 1: Reputation — club won't negotiate with low-rep teams
        $reputationModifier = $this->reputationModifier($game->team, $player);
        if ($reputationModifier < 0.50) {
            return [
                'result' => 'rejected',
                'disposition' => 0.10,
                'rejection_reason' => 'reputation',
            ];
        }

        $importance = $this->playerImportance($player);

        // If the club has publicly signalled willingness to part with the player
        // (listed for sale or actively loan-searched), bypass the "key player" gate —
        // rejecting on importance grounds would contradict the club's own market stance.
        $publicMarketSignal = $player->isTransferListed() || $player->hasActiveLoanSearch();

        // Gate 1: Key player — club refuses to loan
        if (! $publicMarketSignal && $importance > 0.70) {
            return [
                'result' => 'rejected',
                'disposition' => 0.15,
                'rejection_reason' => 'key_player',
            ];
        }

        // Calculate disposition for mood indicator
        $disposition = 0.50;
        $disposition += (1.0 - $importance) * 0.30;
        $disposition += ($reputationModifier - 0.50) * 0.20;
        $disposition = max(0.10, min(0.95, $disposition));

        // Gate 2: Player willingness — player may not want to join
        $willingness = $this->playerTransferWillingness($player, $game, $importance);
        if (in_array($willingness['label'], ['not_interested', 'reluctant'])) {
            return [
                'result' => 'rejected',
                'disposition' => $disposition,
                'rejection_reason' => 'player_refused',
            ];
        }

        return [
            'result' => 'accepted',
            'disposition' => $disposition,
            'rejection_reason' => null,
        ];
    }

    // =========================================
    // PRE-CONTRACT OFFER EVALUATION
    // =========================================

    /**
     * Evaluate whether a player accepts a pre-contract offer based on offered wage vs demand,
     * reputation gap, and player ambition.
     *
     * @return array{accepted: bool, message: string}
     */
    public function evaluatePreContractOffer(GamePlayer $player, int $offeredWage, int $wageDemand, Team $biddingTeam): array
    {
        if ($offeredWage >= $wageDemand) {
            $baseChance = 65;
        } elseif ($offeredWage >= (int) ($wageDemand * 0.85)) {
            $baseChance = 25;
        } else {
            return [
                'accepted' => false,
                'message' => __('messages.pre_contract_rejected', ['player' => $player->name]),
            ];
        }

        // Apply reputation modifier
        $reputationModifier = $this->reputationModifier($biddingTeam, $player);

        // Apply ambition modifier: top-tier players resist joining clubs below their level
        $gameId = $player->game_id;
        $clubReputationIndex = ClubProfile::getReputationTierIndex(
            TeamReputation::resolveLevel($gameId, $biddingTeam->id)
        );
        $playerTierIndex = ($player->tier ?? 1) - 1; // normalize to 0-4
        $tierGap = $playerTierIndex - $clubReputationIndex;
        $ambitionModifier = $tierGap > 0
            ? max(0.10, 1.0 - $tierGap * 0.25)
            : 1.0;

        $finalChance = (int) ($baseChance * $reputationModifier * $ambitionModifier);

        $accepted = rand(1, 100) <= $finalChance;

        if ($accepted) {
            return [
                'accepted' => true,
                'message' => __('messages.pre_contract_accepted', ['player' => $player->name]),
            ];
        }

        return [
            'accepted' => false,
            'message' => __('messages.pre_contract_rejected', ['player' => $player->name]),
        ];
    }

    // =========================================
    // MOOD INDICATORS
    // =========================================

    /**
     * Get mood indicator for a disposition score.
     *
     * Unified method replacing duplicated mood indicators across services.
     * All contexts use the same 0.65/0.40 thresholds.
     *
     * @param string $context One of: 'renewal', 'transfer_sell', 'transfer_sign', 'loan'
     * @return array{label: string, color: string}
     */
    public function moodIndicator(float $disposition, string $context = 'renewal'): array
    {
        $keys = match ($context) {
            'transfer_sell' => ['transfers.mood_willing_sell', 'transfers.mood_open_sell', 'transfers.mood_reluctant_sell'],
            'transfer_sign' => ['transfers.mood_willing_sign', 'transfers.mood_open_sign', 'transfers.mood_reluctant_sign'],
            'loan' => ['transfers.mood_willing_loan', 'transfers.mood_open_loan', 'transfers.mood_reluctant_loan'],
            default => ['transfers.mood_willing', 'transfers.mood_open', 'transfers.mood_reluctant'],
        };

        if ($disposition >= 0.65) {
            return ['label' => __($keys[0]), 'color' => 'green'];
        }
        if ($disposition >= 0.40) {
            return ['label' => __($keys[1]), 'color' => 'amber'];
        }

        return ['label' => __($keys[2]), 'color' => 'red'];
    }

    /**
     * Build the mood indicator from the player transfer willingness score.
     * Maps the 5-level willingness labels to the 3-level mood indicator format.
     *
     * @return array{label: string, color: string}
     */
    public function willingnessMoodIndicator(GamePlayer $player, Game $game): array
    {
        $willingness = $this->playerTransferWillingness($player, $game);

        return match ($willingness['label']) {
            'very_interested', 'open' => ['label' => __('transfers.mood_willing_sign'), 'color' => 'green'],
            'undecided' => ['label' => __('transfers.mood_open_sign'), 'color' => 'amber'],
            default => ['label' => __('transfers.mood_reluctant_sign'), 'color' => 'red'],
        };
    }
}
