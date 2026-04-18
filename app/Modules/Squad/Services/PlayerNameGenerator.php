<?php

namespace App\Modules\Squad\Services;

use Faker\Factory;
use Faker\Generator;

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
        'Peru' => 'es_PE',
        'Venezuela' => 'es_VE',
        'Mexico' => 'es_ES',
        'Colombia' => 'es_ES',
        'Chile' => 'es_ES',
        'Uruguay' => 'es_AR',
        'Paraguay' => 'es_AR',
        'Bolivia' => 'es_ES',
        'Ecuador' => 'es_ES',
        'Cuba' => 'es_ES',
        'Dominican Republic' => 'es_ES',
        'Honduras' => 'es_ES',
        'Panama' => 'es_ES',

        'England' => 'en_GB',
        'Wales' => 'en_GB',
        'Scotland' => 'en_GB',
        'Northern Ireland' => 'en_GB',
        'Ireland' => 'en_GB',
        'United States' => 'en_US',
        'Nigeria' => 'en_NG',
        'South Africa' => 'en_ZA',
        'Ghana' => 'en_NG',
        'Liberia' => 'en_NG',
        'Sierra Leone' => 'en_NG',
        'Kenya' => 'en_NG',
        'Zambia' => 'en_NG',
        'Zimbabwe' => 'en_NG',
        'Uganda' => 'en_NG',
        'Jamaica' => 'en_GB',
        'Trinidad and Tobago' => 'en_GB',
        'Australia' => 'en_US',
        'New Zealand' => 'en_US',
        'Philippines' => 'en_PH',

        'France' => 'fr_FR',
        'Belgium' => 'fr_BE',
        'Monaco' => 'fr_FR',
        'Senegal' => 'fr_FR',
        "Cote d'Ivoire" => 'fr_FR',
        'Ivory Coast' => 'fr_FR',
        'Cameroon' => 'fr_FR',
        'Mali' => 'fr_FR',
        'Burkina Faso' => 'fr_FR',
        'Guinea' => 'fr_FR',
        'Togo' => 'fr_FR',
        'Benin' => 'fr_FR',
        'Niger' => 'fr_FR',
        'Gabon' => 'fr_FR',
        'Congo' => 'fr_FR',
        'DR Congo' => 'fr_FR',
        'Central African Republic' => 'fr_FR',
        'Chad' => 'fr_FR',
        'Madagascar' => 'fr_FR',
        'Canada' => 'fr_CA',
        'Haiti' => 'fr_FR',
        'Luxembourg' => 'fr_FR',

        'Germany' => 'de_DE',
        'Austria' => 'de_AT',
        'Switzerland' => 'de_DE',
        'Liechtenstein' => 'de_DE',

        'Italy' => 'it_IT',
        'San Marino' => 'it_IT',

        'Portugal' => 'pt_PT',
        'Brazil' => 'pt_BR',
        'Angola' => 'pt_PT',
        'Mozambique' => 'pt_PT',
        'Cape Verde' => 'pt_PT',
        'Guinea-Bissau' => 'pt_PT',
        'Sao Tome and Principe' => 'pt_PT',

        'Netherlands' => 'nl_NL',
        'Suriname' => 'nl_NL',

        'Poland' => 'pl_PL',
        'Czech Republic' => 'cs_CZ',
        'Slovakia' => 'sk_SK',
        'Hungary' => 'hu_HU',
        'Romania' => 'ro_RO',
        'Moldova' => 'ro_RO',
        'Bulgaria' => 'bg_BG',
        'Serbia' => 'sr_RS',
        'Kosovo' => 'sr_RS',
        'Montenegro' => 'sr_RS',
        'Bosnia-Herzegovina' => 'hr_HR',
        'North Macedonia' => 'sr_RS',
        'Croatia' => 'hr_HR',
        'Slovenia' => 'sl_SI',
        'Albania' => 'sr_RS',

        'Russia' => 'ru_RU',
        'Ukraine' => 'uk_UA',
        'Belarus' => 'ru_RU',
        'Lithuania' => 'lt_LT',
        'Latvia' => 'lv_LV',
        'Estonia' => 'et_EE',

        'Greece' => 'el_GR',
        'Cyprus' => 'el_GR',

        'Denmark' => 'da_DK',
        'Sweden' => 'sv_SE',
        'Norway' => 'nb_NO',
        'Finland' => 'fi_FI',
        'Iceland' => 'is_IS',
        'Faroe Islands' => 'is_IS',

        'Türkiye' => 'tr_TR',
        'Turkey' => 'tr_TR',

        'Morocco' => 'ar_SA',
        'Algeria' => 'ar_SA',
        'Tunisia' => 'ar_SA',
        'Libya' => 'ar_SA',
        'Egypt' => 'ar_EG',
        'Saudi Arabia' => 'ar_SA',
        'United Arab Emirates' => 'ar_SA',
        'Qatar' => 'ar_SA',
        'Kuwait' => 'ar_SA',
        'Bahrain' => 'ar_SA',
        'Oman' => 'ar_SA',
        'Yemen' => 'ar_SA',
        'Jordan' => 'ar_JO',
        'Lebanon' => 'ar_JO',
        'Syria' => 'ar_JO',
        'Iraq' => 'ar_JO',
        'Palestine' => 'ar_JO',
        'Sudan' => 'ar_SA',
        'Mauritania' => 'ar_SA',

        'Iran' => 'fa_IR',
        'Israel' => 'he_IL',
        'Georgia' => 'ka_GE',
        'Armenia' => 'hy_AM',
        'Kazakhstan' => 'kk_KZ',

        'Japan' => 'ja_JP',
        'Korea, South' => 'ko_KR',
        'South Korea' => 'ko_KR',
        'China' => 'zh_CN',
        'Taiwan' => 'zh_CN',
        'Thailand' => 'th_TH',
        'Vietnam' => 'vi_VN',
        'Indonesia' => 'id_ID',
        'Malaysia' => 'ms_MY',
        'Bangladesh' => 'bn_BD',
        'Nepal' => 'ne_NP',
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

        return $faker->firstNameMale() . ' ' . $faker->lastName();
    }

    /**
     * Locale used for a given nationality, exposed for tests and debugging.
     */
    public function localeFor(string $nationality): string
    {
        return self::NATIONALITY_LOCALES[$nationality] ?? self::FALLBACK_LOCALE;
    }

    private function fakerFor(string $nationality): Generator
    {
        $locale = $this->localeFor($nationality);

        return $this->generators[$locale] ??= Factory::create($locale);
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
