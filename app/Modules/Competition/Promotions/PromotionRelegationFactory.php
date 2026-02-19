<?php

namespace App\Modules\Competition\Promotions;

use App\Modules\Competition\Contracts\PromotionRelegationRule;
use App\Modules\Competition\Services\CountryConfig;

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
                    $tiers = $this->countryConfig->tiers($countryCode);
                    $competitionId = $promotion['bottom_division'];
                    $tierConfig = collect($tiers)->first(fn ($t) => $t['competition'] === $competitionId);
                    $teamCount = $tierConfig['teams'] ?? 22;

                    $playoffGenerator = new ($promotion['playoff_generator'])(
                        competitionId: $competitionId,
                        qualifyingPositions: $promotion['playoff_positions'] ?? [],
                        directPromotionPositions: $promotion['direct_promotion_positions'],
                        triggerMatchday: ($teamCount - 1) * 2,
                    );
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
