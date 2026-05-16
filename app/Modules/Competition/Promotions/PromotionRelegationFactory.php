<?php

namespace App\Modules\Competition\Promotions;

use App\Models\Game;
use App\Modules\Competition\Contracts\PromotionRelegationRule;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\ReserveTeamFilter;

class PromotionRelegationFactory
{
    private ?array $rules = null;

    public function __construct(
        private CountryConfig $countryConfig,
        private ReserveTeamFilter $reserveTeamFilter,
        private PlayoffGeneratorFactory $playoffGeneratorFactory,
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
     * Did $teamId (defaulting to the game's managed team) get promoted out
     * of its current competition this season? Swallows playoff/runtime errors
     * because callers (offer generation, season snapshot) treat them as
     * "not promoted" rather than failing the surrounding flow.
     */
    public function wasTeamPromoted(Game $game, ?string $teamId = null): bool
    {
        $rule = $this->forCompetition($game->competition_id);
        if (!$rule) {
            return false;
        }

        try {
            $promoted = $rule->getPromotedTeams($game);
        } catch (\Throwable) {
            return false;
        }

        $target = $teamId ?? $game->team_id;

        foreach ($promoted as $entry) {
            if (($entry['teamId'] ?? null) === $target) {
                return true;
            }
        }

        return false;
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
                // Custom rule classes (SelfSwappingPromotionRule implementations)
                // own their own wiring. We re-use the PlayoffGeneratorFactory's
                // existing instance so there's exactly one generator per rule.
                if (!empty($promotion['rule_class'])) {
                    $playoffGenerator = null;
                    if (!empty($promotion['playoff_generator'])) {
                        $firstSource = $promotion['playoff_source_divisions'][0]
                            ?? $promotion['bottom_division'];
                        $playoffGenerator = $this->playoffGeneratorFactory->forCompetition($firstSource);
                    }

                    $rules[] = new ($promotion['rule_class'])(
                        playoffGenerator: $playoffGenerator,
                        reserveTeamFilter: $this->reserveTeamFilter,
                    );
                    continue;
                }

                $playoffGenerator = null;
                if (isset($promotion['playoff_generator'])) {
                    $tiers = $this->countryConfig->tiers($countryCode);
                    $competitionId = $promotion['bottom_division'];
                    $tierConfig = collect($tiers)->first(fn ($t) => $t['competition'] === $competitionId);
                    $teamCount = $tierConfig['teams'] ?? 22;

                    $playoffGenerator = new ($promotion['playoff_generator'])(
                        competitionId: $competitionId,
                        directCount: $promotion['direct_count'],
                        playoffCount: $promotion['playoff_count'] ?? 0,
                        triggerMatchday: ($teamCount - 1) * 2,
                    );
                }

                $rules[] = new ConfigDrivenPromotionRule(
                    topDivision: $promotion['top_division'],
                    bottomDivision: $promotion['bottom_division'],
                    relegatedPositions: $promotion['relegated_positions'],
                    directCount: $promotion['direct_count'],
                    playoffCount: $promotion['playoff_count'] ?? 0,
                    playoffGenerator: $playoffGenerator,
                    reserveTeamFilter: $this->reserveTeamFilter,
                );
            }
        }

        return $rules;
    }
}
