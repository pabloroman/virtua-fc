<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\Contracts\CompetitionConfig;

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
            ->filter(fn (array $config, string $code) => $code !== 'XX' && !empty($config['tiers']) && empty($config['tournament']))
            ->keys()
            ->all();
    }

    /**
     * Get all tournament country codes (e.g., World Cup).
     *
     * @return string[]
     */
    public function tournamentCountryCodes(): array
    {
        return collect($this->allCountries())
            ->filter(fn (array $config) => !empty($config['tournament']) && !empty($config['tiers']))
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
     * Get cup winner qualification slot config for a country.
     *
     * @return array{cup: string, competition: string, league: string}|null
     */
    public function cupWinnerSlot(string $countryCode): ?array
    {
        return $this->get($countryCode)['cup_winner_slot'] ?? null;
    }

    /**
     * Get supercup config for a country.
     *
     * @return array{competition: string, cup: string, league: string, cup_final_round: int, cup_entry_round?: int}|null
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

            // Check domestic cups
            foreach ($config['domestic_cups'] ?? [] as $cupId => $cupConfig) {
                if ($cupId === $competitionId && isset($cupConfig['config_class'])) {
                    return $cupConfig['config_class'];
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
     * Get support team config for a country.
     *
     * @return array{transfer_pool?: array, continental?: array}
     */
    public function support(string $countryCode): array
    {
        return $this->get($countryCode)['support'] ?? [];
    }

    /**
     * Get transfer pool competition IDs for a country.
     *
     * @return string[]
     */
    public function transferPoolIds(string $countryCode): array
    {
        return array_keys($this->support($countryCode)['transfer_pool'] ?? []);
    }

    /**
     * Get continental support competition IDs for a country.
     *
     * @return string[]
     */
    public function continentalSupportIds(string $countryCode): array
    {
        return array_keys($this->support($countryCode)['continental'] ?? []);
    }

    /**
     * Get all competition IDs that need GamePlayer initialization for a country.
     * Returns them in dependency order: tiers first, then transfer pool, then continental.
     *
     * @return string[]
     */
    public function playerInitializationOrder(string $countryCode): array
    {
        $ids = [];

        // 1. Playable tier competitions
        foreach ($this->tiers($countryCode) as $tier) {
            $ids[] = $tier['competition'];
        }

        // 2. Transfer pool competitions
        foreach ($this->transferPoolIds($countryCode) as $poolId) {
            $ids[] = $poolId;
        }

        // 3. Continental support competitions
        foreach ($this->continentalSupportIds($countryCode) as $continentalId) {
            $ids[] = $continentalId;
        }

        return $ids;
    }

    /**
     * Get all Swiss format competition IDs for a country.
     * Merges continental support IDs with any swiss_format competitions.
     *
     * @return string[]
     */
    public function swissFormatCompetitionIds(string $countryCode): array
    {
        $continentalIds = $this->continentalSupportIds($countryCode);
        $swissIds = \App\Models\Competition::where('handler_type', 'swiss_format')->pluck('id')->toArray();

        return array_unique(array_merge($continentalIds, $swissIds));
    }

    /**
     * Get all countries config.
     */
    private function allCountries(): array
    {
        return config('countries', []);
    }
}
