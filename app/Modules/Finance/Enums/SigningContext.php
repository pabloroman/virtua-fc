<?php

namespace App\Modules\Finance\Enums;

/**
 * What kind of signing the wage-budget gate is evaluating.
 *
 * Determines which gate(s) apply:
 *   - TRANSFER / FREE_AGENT / LOAN_IN: in-season, charges current-season headroom
 *     (and next-season too if the contract extends past June 30).
 *   - PRE_CONTRACT: never charges current season; only next-season headroom.
 *   - RENEWAL: soft check — wage increase against current-season headroom for
 *     this season and next; never rejects (excess flows into carried_debt).
 */
enum SigningContext: string
{
    case TRANSFER = 'transfer';
    case FREE_AGENT = 'free_agent';
    case PRE_CONTRACT = 'pre_contract';
    case RENEWAL = 'renewal';
    case LOAN_IN = 'loan_in';

    /**
     * Whether the gate is a hard reject (true) or a soft warning that lets the
     * signing through and lets the deficit settle into carried_debt (false).
     */
    public function isHardReject(): bool
    {
        return match ($this) {
            self::RENEWAL => false,
            default => true,
        };
    }

    /**
     * Whether the current-season cap applies for this context.
     */
    public function appliesCurrentSeason(): bool
    {
        return match ($this) {
            self::PRE_CONTRACT => false,
            default => true,
        };
    }

    /**
     * Whether the next-season cap applies for this context. A pre-contract
     * always commits next season; transfers/free-agents/loans-in do if the
     * contract's signed wage runs past June 30 (handled by the service).
     */
    public function appliesNextSeason(): bool
    {
        return match ($this) {
            self::LOAN_IN => false,
            default => true,
        };
    }
}
