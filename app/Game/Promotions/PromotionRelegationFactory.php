<?php

namespace App\Game\Promotions;

use App\Game\Contracts\PromotionRelegationRule;

class PromotionRelegationFactory
{
    public function __construct(
        private SpanishPromotionRule $spanish,
        // Add more rules here as needed:
        // private EnglishPromotionRule $english,
    ) {}

    /**
     * Get all configured promotion/relegation rules.
     *
     * @return PromotionRelegationRule[]
     */
    public function all(): array
    {
        return [
            $this->spanish,
            // $this->english,
        ];
    }

    /**
     * Get rule for a specific division pair.
     */
    public function forDivisions(string $topDivision, string $bottomDivision): ?PromotionRelegationRule
    {
        foreach ($this->all() as $rule) {
            if ($rule->getTopDivision() === $topDivision &&
                $rule->getBottomDivision() === $bottomDivision) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Get rule that involves a specific competition (either as top or bottom division).
     */
    public function forCompetition(string $competitionId): ?PromotionRelegationRule
    {
        foreach ($this->all() as $rule) {
            if ($rule->getTopDivision() === $competitionId ||
                $rule->getBottomDivision() === $competitionId) {
                return $rule;
            }
        }

        return null;
    }
}
