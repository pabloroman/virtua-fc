<?php

namespace App\Modules\Competition\Exceptions;

use LogicException;

/**
 * Thrown by {@see \App\Modules\Competition\Promotions\CountryPromotionRelegationPlanner}
 * when the plan would leave a reserve team sharing a competition with its parent.
 *
 * In practice this is almost always reachable only when the *input* snapshot
 * already has a reserve coexisting with (or inverted above) its parent — data
 * drift left behind by a prior incomplete season transition. The
 * PromotionRelegationProcessor catches this specific type to attempt an
 * in-band, single-shot self-heal before falling back to manual repair.
 *
 * Extends LogicException so existing `catch (\LogicException)` / `catch
 * (\Throwable)` sites keep working unchanged; only the planner's coexistence
 * branch throws this subtype, leaving genuine planner-bug LogicExceptions
 * (unbalanced plan, double move) as the bare base type.
 */
class ReserveParentCoexistenceException extends LogicException
{
    /**
     * @param  list<array{reserve: string, parent: string, competition: string}>  $violations
     */
    private function __construct(string $message, private readonly array $violations)
    {
        parent::__construct($message);
    }

    /**
     * @param  list<array{reserve: string, parent: string, competition: string}>  $violations
     */
    public static function forViolations(array $violations): self
    {
        $parts = array_map(
            fn (array $v) => "reserve={$v['reserve']} parent={$v['parent']} competition={$v['competition']}",
            $violations,
        );

        // Keep the historical "Planner produced a coexistence violation:" prefix
        // so existing log searches / alerts continue to match.
        return new self(
            'Planner produced a coexistence violation: ' . implode('; ', $parts) . '.',
            $violations,
        );
    }

    /**
     * @return list<array{reserve: string, parent: string, competition: string}>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
