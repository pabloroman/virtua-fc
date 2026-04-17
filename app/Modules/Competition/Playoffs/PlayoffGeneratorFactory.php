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
            $flattenedTiers = $countryConfig->flattenedTiers($code);
            foreach ($countryConfig->promotions($code) as $rule) {
                if (empty($rule['playoff_generator'])) {
                    continue;
                }

                // By default the generator is registered under the rule's
                // bottom division, so LeagueWithPlayoffHandler triggers it
                // when that league's regular season ends. Multi-feeder
                // formats (e.g. Primera RFEF, which pulls from both ESP3A and
                // ESP3B) can declare a list of source divisions via the
                // optional 'playoff_source_divisions' key so a single
                // generator instance fires from any of them.
                $sourceDivisions = $rule['playoff_source_divisions'] ?? [$rule['bottom_division']];
                $targetCompetitionId = $rule['playoff_competition'] ?? $rule['bottom_division'];

                // Derive the trigger matchday from the feeder league's team
                // count. For Primera RFEF's two groups of 20 this is 38.
                $firstSource = $sourceDivisions[0];
                $tierConfig = collect($flattenedTiers)->first(fn ($t) => $t['competition'] === $firstSource);
                $teamCount = $tierConfig['teams'] ?? 22;
                $triggerMatchday = ($teamCount - 1) * 2;

                $generator = new ($rule['playoff_generator'])(
                    competitionId: $targetCompetitionId,
                    qualifyingPositions: $rule['playoff_positions'] ?? [],
                    directPromotionPositions: $rule['direct_promotion_positions'],
                    triggerMatchday: $triggerMatchday,
                );

                foreach ($sourceDivisions as $sourceDivision) {
                    $this->generators[$sourceDivision] = $generator;
                }
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
     * When a generator is registered under multiple source divisions (e.g.
     * Primera RFEF's shared generator under both ESP3A and ESP3B) the
     * instance is returned only once.
     *
     * @return PlayoffGenerator[]
     */
    public function all(): array
    {
        $seen = [];
        $unique = [];
        foreach ($this->generators as $generator) {
            $hash = spl_object_hash($generator);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $unique[] = $generator;
        }
        return $unique;
    }
}
