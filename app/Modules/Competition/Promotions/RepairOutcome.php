<?php

namespace App\Modules\Competition\Promotions;

/**
 * Result classification for {@see ReserveParentCoexistenceRepairer}.
 *
 *  - NothingToFix: no reserve/parent inversion or coexistence detected.
 *  - Repaired:     a deterministic 1:1 swap was planned (and, after apply(),
 *                  written) to restore the hierarchy.
 *  - Unsafe:       a violation exists but cannot be resolved deterministically
 *                  (multi-league corruption, sibling tier with no single swap
 *                  target, unlocatable slot). The caller must escalate to
 *                  manual repair rather than guess.
 */
enum RepairOutcome
{
    case NothingToFix;
    case Repaired;
    case Unsafe;
}
