<?php

namespace App\Game\Promotions;

use App\Game\Contracts\PromotionRelegationRule;
use App\Game\Services\CountryConfig;

class PromotionRelegationFactory
{
    private ?array $rules = null;

    public function __construct(
        private CountryConfig $countryConfig,
    ) {}

    /**
     * Get all configured promotion/relegation rules.
     *
     * @return PromotionRelegationRule[]
     */
    public function all(): array
    {
        if ($this->rules === null) {
            $this->rules = $this->buildRules();
        }

        return $this->rules;
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

    /**
     * Build rules from country config.
     *
     * @return PromotionRelegationRule[]
     */
    private function buildRules(): array
    {
        $rules = [];

        foreach ($this->countryConfig->allCountryCodes() as $countryCode) {
            foreach ($this->countryConfig->promotions($countryCode) as $promotion) {
                $playoffGenerator = null;
                if (isset($promotion['playoff_generator'])) {
                    $playoffGenerator = app($promotion['playoff_generator']);
                }

                $rules[] = new ConfigDrivenPromotionRule(
                    topDivision: $promotion['top_division'],
                    bottomDivision: $promotion['bottom_division'],
                    relegatedPositions: $promotion['relegated_positions'],
                    directPromotionPositions: $promotion['direct_promotion_positions'],
                    playoffGenerator: $playoffGenerator,
                );
            }
        }

        return $rules;
    }
}
