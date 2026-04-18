<?php

namespace App\Modules\Squad\Services;

use Faker\Factory;
use Faker\Generator;
use Illuminate\Support\Str;

/**
 * Generates player names (first + last) from Faker, locale-scoped by nationality.
 *
 * Replaces the old static JSON name pool (`data/academy/players.json`) so that
 * generated players — youth academy prospects, replenishment players, and squad
 * top-ups — draw from an effectively unlimited, culturally appropriate name space
 * per nationality. Using Faker avoids collisions between teams that a small
 * curated pool (~100 Spanish names) made inevitable after a few seasons.
 */
class PlayerNameGenerator
{
    /**
     * Map of in-game nationality strings to Faker locales. Entries are limited
     * to nationalities that appear in game data; anything else falls back to
     * `self::FALLBACK_LOCALE`. When multiple Faker locales exist for a country
     * (e.g. nl_BE / fr_BE for Belgium) we pick the most widely represented.
     */
    private const NATIONALITY_LOCALES = [
        'Spain' => 'es_ES',
        'Argentina' => 'es_AR',
        'Colombia' => 'es_ES',
        'Chile' => 'es_ES',
        'Uruguay' => 'es_AR',
        'Ecuador' => 'es_ES',

        'England' => 'en_GB',
        'Wales' => 'en_GB',
        'Scotland' => 'en_GB',
        'Northern Ireland' => 'en_GB',
        'Ireland' => 'en_GB',
        'Nigeria' => 'en_NG',
        'Ghana' => 'en_NG',

        'France' => 'fr_FR',
        'Belgium' => 'fr_BE',
        'Senegal' => 'fr_FR',
        "Cote d'Ivoire" => 'fr_FR',
        'Ivory Coast' => 'fr_FR',
        'Cameroon' => 'fr_FR',

        'Germany' => 'de_DE',
        'Austria' => 'de_AT',

        'Italy' => 'it_IT',

        'Portugal' => 'pt_PT',
        'Brazil' => 'pt_BR',

        'Netherlands' => 'nl_NL',

        'Poland' => 'pl_PL',
        'Czech Republic' => 'cs_CZ',
        'Slovakia' => 'sk_SK',
        'Hungary' => 'hu_HU',
        'Romania' => 'ro_RO',
        'Moldova' => 'ro_RO',
        'Bulgaria' => 'bg_BG',
        'Serbia' => 'sr_RS',
        'Croatia' => 'hr_HR',
        'Slovenia' => 'sl_SI',
        'Albania' => 'sr_RS',

        'Russia' => 'ru_RU',
        'Ukraine' => 'uk_UA',

        'Greece' => 'el_GR',

        'Denmark' => 'da_DK',
        'Sweden' => 'sv_SE',
        'Norway' => 'nb_NO',

        'Türkiye' => 'tr_TR',
        'Turkey' => 'tr_TR',

        'Morocco' => 'ar_Latn',
        'Algeria' => 'ar_Latn',
        'Tunisia' => 'ar_Latn',
        'Egypt' => 'ar_Latn',
    ];

    private const FALLBACK_LOCALE = 'en_US';

    /**
     * Regional naming overrides. These codes correspond to
     * {@see \App\Modules\Squad\Configs\TeamRegionalOrigins} values and are
     * served by custom Faker Person providers shipped with the app (Faker
     * has no native eu_ES or ca_ES locale).
     */
    private const REGION_PROVIDERS = [
        'basque' => \App\Support\Faker\Provider\eu_ES\Person::class,
        'catalan' => \App\Support\Faker\Provider\ca_ES\Person::class,
    ];

    /**
     * Locale-level overrides for Faker locales whose native-script names don't
     * survive transliteration to readable Latin. Arabic is consonantal, so a
     * character-level romanisation (voku or ICU) produces consonant clusters
     * rather than names — we ship our own pool of Latin-script Arabic names
     * instead. Keyed by the pseudo-locale stored in {@see NATIONALITY_LOCALES}.
     */
    private const CUSTOM_LOCALE_PROVIDERS = [
        'ar_Latn' => \App\Support\Faker\Provider\ar_Latn\Person::class,
    ];

    /**
     * Cache of Faker generators keyed by locale. Faker\Factory::create() loads
     * locale providers via reflection, so reusing generators across calls keeps
     * bulk generation (season start, youth batches) cheap.
     *
     * @var array<string, Generator>
     */
    private array $generators = [];

    /**
     * Generate a full name (first + last) appropriate for the given nationality.
     *
     * When $region is supplied (Basque/Catalan club), the region's custom Faker
     * provider generates the name instead of the nationality-derived locale.
     * Data-layer nationality stays whatever the caller chose — only the name
     * source changes.
     */
    public function generate(string $nationality, ?string $region = null): string
    {
        $faker = $region !== null && isset(self::REGION_PROVIDERS[$region])
            ? $this->fakerForRegion($region)
            : $this->fakerFor($nationality);

        $name = $faker->firstNameMale() . ' ' . $faker->lastName();

        // Faker ships names in each locale's native script (Greek, Cyrillic,
        // Arabic, CJK, etc.). The game UI assumes Latin script everywhere, so
        // transliterate whenever the output contains non-Latin codepoints.
        // Latin diacritics (ñ, é, ü, ł) are preserved — they're still Latin.
        if (preg_match('/[^\p{Latin}\p{Common}\p{Inherited}]/u', $name) === 1) {
            $name = Str::transliterate($name);
        }

        // Belt-and-braces cleanup: a few Faker locales (notably ar_SA) emit
        // pre-transliterated strings peppered with symbols like '@' or '"' that
        // sit in the Common script and so survive both the detection regex and
        // Str::transliterate(). Strip anything that isn't a Latin letter or
        // routine name punctuation, then collapse the whitespace that leaves.
        $name = preg_replace("/[^\\p{Latin}\\s\\-'.]/u", '', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    /**
     * Locale used for a given nationality, exposed for tests and debugging.
     */
    public function localeFor(string $nationality): string
    {
        return self::NATIONALITY_LOCALES[$nationality] ?? self::FALLBACK_LOCALE;
    }

    /**
     * Every nationality this generator can produce believable names for. Callers
     * that need to pick a random nationality (e.g. the 25% foreign branch in
     * {@see PlayerGeneratorService}) should draw from this list so new entries
     * automatically become drawable and removed ones stop leaking through as
     * en_US fallbacks.
     *
     * @return string[]
     */
    public static function supportedNationalities(): array
    {
        return array_keys(self::NATIONALITY_LOCALES);
    }

    private function fakerFor(string $nationality): Generator
    {
        $locale = $this->localeFor($nationality);

        if (isset(self::CUSTOM_LOCALE_PROVIDERS[$locale])) {
            return $this->fakerForCustomLocale($locale);
        }

        return $this->generators[$locale] ??= Factory::create($locale);
    }

    /**
     * Build (and cache) a Faker Generator backed by one of our custom
     * locale-level Person providers (see {@see CUSTOM_LOCALE_PROVIDERS}).
     * Mirrors {@see fakerForRegion()} but keyed by locale instead of region.
     */
    private function fakerForCustomLocale(string $locale): Generator
    {
        $cacheKey = 'locale:' . $locale;

        if (! isset($this->generators[$cacheKey])) {
            $providerClass = self::CUSTOM_LOCALE_PROVIDERS[$locale];
            $generator = new Generator();
            $generator->addProvider(new $providerClass($generator));
            $this->generators[$cacheKey] = $generator;
        }

        return $this->generators[$cacheKey];
    }

    /**
     * Build (and cache) a Faker Generator backed by one of our custom regional
     * Person providers. Faker\Factory::create() can't find these because they
     * live outside the Faker\Provider namespace, so we assemble the Generator
     * directly and inject only the Person provider — the only provider needed
     * for firstNameMale() and lastName().
     */
    private function fakerForRegion(string $region): Generator
    {
        $cacheKey = 'region:' . $region;

        if (! isset($this->generators[$cacheKey])) {
            $providerClass = self::REGION_PROVIDERS[$region];
            $generator = new Generator();
            $generator->addProvider(new $providerClass($generator));
            $this->generators[$cacheKey] = $generator;
        }

        return $this->generators[$cacheKey];
    }
}
