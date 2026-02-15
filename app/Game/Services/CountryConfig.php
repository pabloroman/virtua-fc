<?php

namespace App\Game\Services;

use App\Game\Contracts\CompetitionConfig;

class CountryConfig
{
    /**
     * Get all configured country codes.
     *
     * @return string[]
     */
    public function allCountryCodes(): array
    {
        return array_keys($this->allCountries());
    }

    /**
     * Get all playable country codes (countries with tiers, excluding test).
     *
     * @return string[]
     */
    public function playableCountryCodes(): array
    {
        return collect($this->allCountries())
            ->filter(fn (array $config, string $code) => $code !== 'XX' && !empty($config['tiers']))
            ->keys()
            ->all();
    }

    /**
     * Get the full config array for a country.
     */
    public function get(string $countryCode): ?array
    {
        return $this->allCountries()[$countryCode] ?? null;
    }

    /**
     * Get the country name.
     */
    public function name(string $countryCode): ?string
    {
        return $this->get($countryCode)['name'] ?? null;
    }

    /**
     * Get the flag code for a country.
     */
    public function flag(string $countryCode): ?string
    {
        return $this->get($countryCode)['flag'] ?? null;
    }

    /**
     * Get tier configs for a country.
     *
     * @return array<int, array{competition: string, teams: int, config_class?: class-string}>
     */
    public function tiers(string $countryCode): array
    {
        return $this->get($countryCode)['tiers'] ?? [];
    }

    /**
     * Get the competition ID for a specific tier.
     */
    public function competitionForTier(string $countryCode, int $tier): ?string
    {
        return $this->tiers($countryCode)[$tier]['competition'] ?? null;
    }

    /**
     * Find the country code that owns a given competition ID.
     */
    public function countryForCompetition(string $competitionId): ?string
    {
        foreach ($this->allCountries() as $code => $config) {
            // Check tiers
            foreach ($config['tiers'] ?? [] as $tier) {
                if ($tier['competition'] === $competitionId) {
                    return $code;
                }
            }

            // Check domestic cups
            foreach (array_keys($config['domestic_cups'] ?? []) as $cupId) {
                if ($cupId === $competitionId) {
                    return $code;
                }
            }

            // Check supercup
            if (($config['supercup']['competition'] ?? null) === $competitionId) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Get promotion/relegation rules for a country.
     *
     * @return array<array{top_division: string, bottom_division: string, relegated_positions: int[], direct_promotion_positions: int[], playoff_positions?: int[], playoff_generator?: class-string}>
     */
    public function promotions(string $countryCode): array
    {
        return $this->get($countryCode)['promotions'] ?? [];
    }

    /**
     * Get continental qualification slots for a country.
     *
     * @return array<string, array<string, int[]>>
     */
    public function continentalSlots(string $countryCode): array
    {
        return $this->get($countryCode)['continental_slots'] ?? [];
    }

    /**
     * Get supercup config for a country.
     *
     * @return array{competition: string, cup: string, league: string, cup_final_round: int}|null
     */
    public function supercup(string $countryCode): ?array
    {
        return $this->get($countryCode)['supercup'] ?? null;
    }

    /**
     * Get domestic cup IDs for a country.
     *
     * @return string[]
     */
    public function domesticCupIds(string $countryCode): array
    {
        return array_keys($this->get($countryCode)['domestic_cups'] ?? []);
    }

    /**
     * Get the CompetitionConfig class for a competition ID, checking country configs.
     *
     * @return class-string<CompetitionConfig>|null
     */
    public function configClassForCompetition(string $competitionId): ?string
    {
        foreach ($this->allCountries() as $config) {
            // Check tiers
            foreach ($config['tiers'] ?? [] as $tier) {
                if ($tier['competition'] === $competitionId && isset($tier['config_class'])) {
                    return $tier['config_class'];
                }
            }

            // Check continental competitions
            foreach ($config['continental_competitions'] ?? [] as $continentalId => $continentalConfig) {
                if ($continentalId === $competitionId && isset($continentalConfig['config_class'])) {
                    return $continentalConfig['config_class'];
                }
            }
        }

        return null;
    }

    /**
     * Get all countries config.
     */
    private function allCountries(): array
    {
        return config('countries', []);
    }
}
