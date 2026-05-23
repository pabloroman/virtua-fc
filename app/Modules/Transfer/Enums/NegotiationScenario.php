<?php

namespace App\Modules\Transfer\Enums;

use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;
use Carbon\Carbon;

enum NegotiationScenario: string
{
    case RENEWAL = 'renewal';
    case TRANSFER = 'transfer';
    case PRE_CONTRACT = 'pre_contract';
    case FREE_AGENT = 'free_agent';

    /**
     * Starting disposition before any factor adjustments.
     */
    public function baseDisposition(): float
    {
        return 0.5;
    }

    /**
     * How much disposition translates into wage flexibility.
     * Lower = player demands closer to their full ask.
     *
     * Renewals taper by player tier: Tier 1 (fringe, no outside market) is the
     * most flexible and will renew at his current wage; Tier 2–3 are somewhat
     * flexible; Tier 4–5 keep the current baseline because other clubs would
     * sign them. Other scenarios always use the flat 0.20.
     */
    public function flexibilityRatio(?int $tier = null): float
    {
        if ($this === self::RENEWAL && $tier !== null) {
            return match ($tier) {
                1 => 0.30,
                2, 3 => 0.25,
                default => 0.20,
            };
        }

        return 0.2;
    }

    /**
     * Wage premium multiplier applied on top of the base market wage.
     *
     * Renewals taper by player tier so leverage matches the real market:
     * Tier 1 opens with a small 1.05x ask but accepts current wage when
     * pressed (see flexibilityRatio); Tier 2–3 a modest 1.10x; Tier 4–5 keep
     * the current 1.15x because they have outside suitors. When tier is
     * unavailable, fall back to the previous flat 1.15x.
     */
    public function wagePremium(int $marketValueCents, ?int $tier = null): float
    {
        return match ($this) {
            self::RENEWAL => self::renewalPremiumForTier($tier),
            self::TRANSFER, self::FREE_AGENT => 1.15,
            self::PRE_CONTRACT => self::preContractPremium($marketValueCents),
        };
    }

    private static function renewalPremiumForTier(?int $tier): float
    {
        return match ($tier) {
            1 => 1.05,
            2, 3 => 1.10,
            default => 1.15,
        };
    }

    /**
     * How many years the player wants on their new contract.
     */
    public function preferredContractYears(int $age): int
    {
        return match (true) {
            $age >= PlayerAge::PRIME_END => 1,
            $age >= PlayerAge::primePhaseAge(0.6) => 2,
            $age < PlayerAge::YOUNG_END => 5,
            default => 3,
        };
    }

    /**
     * Status updates to apply on the TransferOffer when terms are accepted.
     */
    public function acceptedStatusUpdates(Carbon $currentDate): array
    {
        return match ($this) {
            self::TRANSFER => [],
            self::PRE_CONTRACT => ['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $currentDate],
            // Free-agent signings are parked as agreed; they join the squad
            // after the next match via CompleteAgreedTransfersOnMatchPlayed.
            self::FREE_AGENT => ['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $currentDate],
            self::RENEWAL => [],
        };
    }

    /**
     * Pre-contract wage premium: expiring-contract players demand more
     * because they bring no transfer fee (signing bonus + agent fees + leverage).
     */
    private static function preContractPremium(int $marketValueCents): float
    {
        return match (true) {
            $marketValueCents >= 10_000_000_000 => 1.50, // 100M+
            $marketValueCents >= 5_000_000_000  => 1.45, // 50M+
            $marketValueCents >= 2_000_000_000  => 1.40, // 20M+
            $marketValueCents >= 1_000_000_000  => 1.35, // 10M+
            $marketValueCents >= 500_000_000    => 1.30, // 5M+
            $marketValueCents >= 200_000_000    => 1.25, // 2M+
            default                             => 1.20,
        };
    }
}
