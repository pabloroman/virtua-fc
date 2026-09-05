<?php

namespace App\Modules\Competition\Services;

use App\Modules\Competition\Contracts\CompetitionConfig;
use App\Modules\Competition\Contracts\CupDrawPairingStrategy;

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
            ->filter(fn (array $config) => !empty($config['tiers']) && empty($config['tournament']))
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
     * Get the flag code for a country code.
     *
     * Maps country codes to flag-icon codes (used during seeding).
     * Most codes are just lowercased, except special cases like EN → gb-eng.
     */
    public function flag(string $countryCode): string
    {
        return match ($countryCode) {
            'EN' => 'gb-eng',
            default => strtolower($countryCode),
        };
    }

    /**
     * Get tier configs for a country.
     *
     * @return array<int, array{competition: string, teams: int, config_class?: class-string, siblings?: array<array{competition: string, teams: int, config_class?: class-string}>}>
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
     * Get all competition IDs at a tier, including siblings.
     *
     * Most tiers have a single competition (e.g. ESP1 at tier 1). Primera RFEF
     * is the first to use siblings — tier 3 returns ['ESP3A', 'ESP3B'].
     *
     * @return string[]
     */
    public function tierCompetitionIds(string $countryCode, int $tier): array
    {
        $tierConfig = $this->tiers($countryCode)[$tier] ?? null;
        if (!$tierConfig) {
            return [];
        }

        $ids = [$tierConfig['competition']];

        foreach ($tierConfig['siblings'] ?? [] as $sibling) {
            if (!empty($sibling['competition'])) {
                $ids[] = $sibling['competition'];
            }
        }

        return $ids;
    }

    /**
     * Get every tier config entry (primary + siblings) for a country as a
     * flat list. Useful for seeding, player initialization, and promotion
     * rule lookup.
     *
     * @return array<array{competition: string, teams: int, handler?: string, config_class?: class-string}>
     */
    public function flattenedTiers(string $countryCode): array
    {
        $entries = [];
        foreach ($this->tiers($countryCode) as $tier => $tierConfig) {
            $primary = $tierConfig;
            unset($primary['siblings']);
            $primary['tier'] = $tier;
            $entries[] = $primary;

            foreach ($tierConfig['siblings'] ?? [] as $sibling) {
                $sibling['tier'] = $tier;
                $entries[] = $sibling;
            }
        }
        return $entries;
    }

    /**
     * Get promotion playoff configs for a country (e.g. Primera RFEF's ESP3PO).
     *
     * @return array<string, array{handler?: string, config_class?: class-string, parent_tier?: int}>
     */
    public function promotionPlayoffs(string $countryCode): array
    {
        return $this->get($countryCode)['promotion_playoffs'] ?? [];
    }

    /**
     * Get promotion playoff competition IDs for a country.
     *
     * @return string[]
     */
    public function promotionPlayoffIds(string $countryCode): array
    {
        return array_keys($this->promotionPlayoffs($countryCode));
    }

    /**
     * Find the country code that owns a given competition ID.
     */
    public function countryForCompetition(string $competitionId): ?string
    {
        foreach ($this->allCountries() as $code => $config) {
            // Check tiers (including siblings)
            foreach ($config['tiers'] ?? [] as $tier) {
                if ($tier['competition'] === $competitionId) {
                    return $code;
                }
                foreach ($tier['siblings'] ?? [] as $sibling) {
                    if (($sibling['competition'] ?? null) === $competitionId) {
                        return $code;
                    }
                }
            }

            // Check domestic cups
            foreach (array_keys($config['domestic_cups'] ?? []) as $cupId) {
                if ($cupId === $competitionId) {
                    return $code;
                }
            }

            // Check promotion playoffs
            if (array_key_exists($competitionId, $config['promotion_playoffs'] ?? [])) {
                return $code;
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
     * @return array<array{top_division: string, bottom_division: string, relegated_positions: int[], direct_count: int, playoff_count?: int, playoff_generator?: class-string}>
     */
    public function promotions(string $countryCode): array
    {
        return $this->get($countryCode)['promotions'] ?? [];
    }

    /**
     * For a competition that hosts promotion-playoff CupTies, return the
     * feeder league competition IDs whose regular-season standings decide
     * tiebreakers when a two-legged tie is level after extra time (higher
     * finisher wins instead of going to penalties).
     *
     * Returns an empty array for competitions that aren't promotion playoffs
     * (domestic cups, UEFA knockouts, etc.), signalling the default penalty
     * tiebreaker still applies.
     *
     * @return string[]
     */
    public function playoffTiebreakerSources(string $competitionId): array
    {
        foreach ($this->allCountries() as $config) {
            foreach ($config['promotions'] ?? [] as $rule) {
                if (empty($rule['playoff_generator'])) {
                    continue;
                }

                $target = $rule['playoff_competition'] ?? $rule['bottom_division'];
                if ($target !== $competitionId) {
                    continue;
                }

                return $rule['playoff_source_divisions'] ?? [$rule['bottom_division']];
            }
        }

        return [];
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
     * Cup winner qualification slots for a country, in the order they should
     * be applied. A country with two cups feeding two different European
     * competitions (England: FA Cup to the Europa League, EFL Cup to the
     * Conference League) declares the better slot first, so the second
     * cascade sees what the first handed out.
     *
     * @return array<int, array{cup: string, competition: string, league: string}>
     */
    public function cupWinnerSlots(string $countryCode): array
    {
        return $this->get($countryCode)['cup_winner_slot'] ?? [];
    }

    /**
     * Get supercup config for a country.
     *
     * @return array{competition: string, cup: string, league: string, teams?: int, cup_entry_round?: int}|null
     */
    public function supercup(string $countryCode): ?array
    {
        return $this->get($countryCode)['supercup'] ?? null;
    }

    /**
     * Number of clubs in a country's supercup: 4 for a final four (both cup
     * finalists plus the league's top two), 2 for a champions-v-cup-winner
     * one-off. Defaults to the final four, the shape ESPSUP always had.
     */
    public function supercupSize(string $countryCode): int
    {
        return (int) ($this->supercup($countryCode)['teams'] ?? 4);
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
     * The config entry of a domestic cup, looked up by competition ID across
     * every country. Null for anything that isn't a domestic cup (leagues,
     * UEFA competitions, promotion playoffs).
     *
     * @return array{handler?: string, config_class?: class-string, draw_pairing?: class-string, short_name?: string, abbreviation?: string, neutral_venues?: array<string, array{name: string, capacity: int}>}|null
     */
    public function domesticCup(string $competitionId): ?array
    {
        foreach ($this->allCountries() as $config) {
            if (isset($config['domestic_cups'][$competitionId])) {
                return $config['domestic_cups'][$competitionId];
            }
        }

        return null;
    }

    /**
     * Neutral venue for a domestic cup round, if the cup declares one.
     *
     * `neutral_venues` is keyed by round name (e.g. 'cup.final'); a '*' key
     * applies to every round of the competition (final-four supercups).
     *
     * @return array{name: string, capacity: int}|null
     */
    public function neutralVenue(string $competitionId, string $roundName): ?array
    {
        $venues = $this->domesticCup($competitionId)['neutral_venues'] ?? [];

        return $venues[$roundName] ?? $venues['*'] ?? null;
    }

    /**
     * Compact display name declared for a domestic cup, if any.
     */
    public function cupShortName(string $competitionId): ?string
    {
        return $this->domesticCup($competitionId)['short_name'] ?? null;
    }

    /**
     * Ultra-compact tag declared for a domestic cup, if any.
     */
    public function cupAbbreviation(string $competitionId): ?string
    {
        return $this->domesticCup($competitionId)['abbreviation'] ?? null;
    }

    /**
     * Get the cup qualification rule for a specific domestic cup, if defined.
     *
     * Describes which teams from the playable tiers qualify for the cup at
     * the start of the following season. See config/countries.php for shape.
     *
     * @return array{auto_qualify_tiers?: int[], top_per_group?: array<int, int>, target_size?: int}|null
     */
    public function cupQualification(string $countryCode, string $cupId): ?array
    {
        return $this->get($countryCode)['cup_qualification'][$cupId] ?? null;
    }

    /**
     * Get the CompetitionConfig class for a competition ID, checking country configs.
     *
     * @return class-string<CompetitionConfig>|null
     */
    public function configClassForCompetition(string $competitionId): ?string
    {
        foreach ($this->allCountries() as $config) {
            // Check tiers (including siblings)
            foreach ($config['tiers'] ?? [] as $tier) {
                if ($tier['competition'] === $competitionId && isset($tier['config_class'])) {
                    return $tier['config_class'];
                }
                foreach ($tier['siblings'] ?? [] as $sibling) {
                    if (($sibling['competition'] ?? null) === $competitionId && isset($sibling['config_class'])) {
                        return $sibling['config_class'];
                    }
                }
            }

            // Check domestic cups
            foreach ($config['domestic_cups'] ?? [] as $cupId => $cupConfig) {
                if ($cupId === $competitionId && isset($cupConfig['config_class'])) {
                    return $cupConfig['config_class'];
                }
            }

            // Check promotion playoffs
            foreach ($config['promotion_playoffs'] ?? [] as $playoffId => $playoffConfig) {
                if ($playoffId === $competitionId && isset($playoffConfig['config_class'])) {
                    return $playoffConfig['config_class'];
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
     * Get the CupDrawPairingStrategy class for a competition ID.
     *
     * @return class-string<CupDrawPairingStrategy>|null
     */
    public function drawPairingClassForCompetition(string $competitionId): ?string
    {
        foreach ($this->allCountries() as $config) {
            foreach ($config['domestic_cups'] ?? [] as $cupId => $cupConfig) {
                if ($cupId === $competitionId && isset($cupConfig['draw_pairing'])) {
                    return $cupConfig['draw_pairing'];
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

        // 1. Playable tier competitions (including siblings at each tier)
        foreach ($this->tiers($countryCode) as $tier) {
            $ids[] = $tier['competition'];
            foreach ($tier['siblings'] ?? [] as $sibling) {
                if (!empty($sibling['competition'])) {
                    $ids[] = $sibling['competition'];
                }
            }
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
