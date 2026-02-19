<?php

namespace App\Modules\Competition\Playoffs;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\Services\CountryConfig;

class PlayoffGeneratorFactory
{
    /** @var array<string, PlayoffGenerator> */
    private array $generators = [];

    public function __construct(CountryConfig $countryConfig)
    {
        foreach ($countryConfig->allCountryCodes() as $code) {
            $tiers = $countryConfig->tiers($code);
            foreach ($countryConfig->promotions($code) as $rule) {
                if (empty($rule['playoff_generator'])) {
                    continue;
                }

                $competitionId = $rule['bottom_division'];
                $tierConfig = collect($tiers)->first(fn ($t) => $t['competition'] === $competitionId);
                $teamCount = $tierConfig['teams'] ?? 22;
                $triggerMatchday = ($teamCount - 1) * 2;

                $this->generators[$competitionId] = new ($rule['playoff_generator'])(
                    competitionId: $competitionId,
                    qualifyingPositions: $rule['playoff_positions'] ?? [],
                    directPromotionPositions: $rule['direct_promotion_positions'],
                    triggerMatchday: $triggerMatchday,
                );
            }
        }
    }

    /**
     * Get the playoff generator for a competition.
     */
    public function forCompetition(string $competitionId): ?PlayoffGenerator
    {
        return $this->generators[$competitionId] ?? null;
    }

    /**
     * Check if a competition has playoffs configured.
     */
    public function hasPlayoff(string $competitionId): bool
    {
        return $this->forCompetition($competitionId) !== null;
    }

    /**
     * Get all registered playoff generators.
     *
     * @return PlayoffGenerator[]
     */
    public function all(): array
    {
        return array_values($this->generators);
    }
}
