<?php

namespace App\Modules\Transfer\Enums;

use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;

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
        return match ($this) {
            self::PRE_CONTRACT => 0.40,
            default => 0.50,
        };
    }

    /**
     * How much disposition translates into wage flexibility.
     * Lower = player demands closer to their full ask.
     */
    public function flexibilityRatio(): float
    {
        return match ($this) {
            self::PRE_CONTRACT => 0.15,
            self::RENEWAL => 0.18,
            self::TRANSFER, self::FREE_AGENT => 0.30,
        };
    }

    /**
     * Wage premium multiplier applied on top of the base market wage.
     */
    public function wagePremium(int $marketValueCents): float
    {
        return match ($this) {
            self::RENEWAL => 1.15,
            self::PRE_CONTRACT => self::preContractPremium($marketValueCents),
            self::TRANSFER, self::FREE_AGENT => 1.00,
        };
    }

    /**
     * Whether the player refuses to take a pay cut (renewal: current wage is the floor).
     */
    public function hasSalaryFloor(): bool
    {
        return $this === self::RENEWAL;
    }

    /**
     * How many years the player wants on their new contract.
     */
    public function preferredContractYears(int $age): int
    {
        return match ($this) {
            self::RENEWAL => match (true) {
                $age >= PlayerAge::PRIME_END => 1,
                $age < PlayerAge::YOUNG_END => 5,
                default => 3,
            },
            default => match (true) {
                $age >= PlayerAge::PRIME_END => 1,
                $age >= PlayerAge::primePhaseAge(0.6) => 2,
                default => 3,
            },
        };
    }

    /**
     * Mood indicator context key for the UI.
     */
    public function moodContext(): string
    {
        return match ($this) {
            self::RENEWAL => 'renewal',
            default => 'transfer_sign',
        };
    }

    /**
     * Status updates to apply on the TransferOffer when terms are accepted.
     */
    public function acceptedStatusUpdates(\Carbon\Carbon $currentDate): array
    {
        return match ($this) {
            self::TRANSFER => [],
            self::PRE_CONTRACT => ['status' => TransferOffer::STATUS_AGREED, 'resolved_at' => $currentDate],
            self::FREE_AGENT => ['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $currentDate],
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
