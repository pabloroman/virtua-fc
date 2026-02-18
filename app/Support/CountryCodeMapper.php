<?php

namespace App\Support;

/**
 * Maps country names to ISO 3166-1 alpha-2 codes.
 * Includes official ISO names plus common alternatives used in football.
 *
 * @see https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
 */
class CountryCodeMapper
{
    private static array $countryToCode = [
        // A
        'Afghanistan' => 'af',
        'Åland Islands' => 'ax',
        'Albania' => 'al',
        'Algeria' => 'dz',
        'American Samoa' => 'as',
        'Andorra' => 'ad',
        'Angola' => 'ao',
        'Anguilla' => 'ai',
        'Antarctica' => 'aq',
        'Antigua and Barbuda' => 'ag',
        'Argentina' => 'ar',
        'Armenia' => 'am',
        'Aruba' => 'aw',
        'Australia' => 'au',
        'Austria' => 'at',
        'Azerbaijan' => 'az',

        // B
        'Bahamas' => 'bs',
        'Bahrain' => 'bh',
        'Bangladesh' => 'bd',
        'Barbados' => 'bb',
        'Belarus' => 'by',
        'Belgium' => 'be',
        'Belize' => 'bz',
        'Benin' => 'bj',
        'Bermuda' => 'bm',
        'Bhutan' => 'bt',
        'Bolivia' => 'bo',
        'Bolivia, Plurinational State of' => 'bo',
        'Bonaire, Sint Eustatius and Saba' => 'bq',
        'Bosnia and Herzegovina' => 'ba',
        'Bosnia-Herzegovina' => 'ba',
        'Botswana' => 'bw',
        'Bouvet Island' => 'bv',
        'Brazil' => 'br',
        'British Indian Ocean Territory' => 'io',
        'Brunei' => 'bn',
        'Brunei Darussalam' => 'bn',
        'Bulgaria' => 'bg',
        'Burkina Faso' => 'bf',
        'Burundi' => 'bi',

        // C
        'Cabo Verde' => 'cv',
        'Cape Verde' => 'cv',
        'Cambodia' => 'kh',
        'Cameroon' => 'cm',
        'Canada' => 'ca',
        'Cayman Islands' => 'ky',
        'Central African Republic' => 'cf',
        'Chad' => 'td',
        'Chile' => 'cl',
        'China' => 'cn',
        "China PR" => 'cn',
        'Christmas Island' => 'cx',
        'Cocos (Keeling) Islands' => 'cc',
        'Colombia' => 'co',
        'Comoros' => 'km',
        'Congo' => 'cg',
        'Congo, Democratic Republic of the' => 'cd',
        'DR Congo' => 'cd',
        'Democratic Republic of Congo' => 'cd',
        'Cook Islands' => 'ck',
        'Costa Rica' => 'cr',
        "Côte d'Ivoire" => 'ci',
        "Cote d'Ivoire" => 'ci',
        'Ivory Coast' => 'ci',
        'Croatia' => 'hr',
        'Cuba' => 'cu',
        'Curaçao' => 'cw',
        'Curacao' => 'cw',
        'Cyprus' => 'cy',
        'Czechia' => 'cz',
        'Czech Republic' => 'cz',

        // D
        'Denmark' => 'dk',
        'Djibouti' => 'dj',
        'Dominica' => 'dm',
        'Dominican Republic' => 'do',

        // E
        'Ecuador' => 'ec',
        'Egypt' => 'eg',
        'El Salvador' => 'sv',
        'Equatorial Guinea' => 'gq',
        'Eritrea' => 'er',
        'Estonia' => 'ee',
        'Eswatini' => 'sz',
        'Swaziland' => 'sz',
        'Ethiopia' => 'et',

        // F
        'Falkland Islands' => 'fk',
        'Falkland Islands (Malvinas)' => 'fk',
        'Faroe Islands' => 'fo',
        'Fiji' => 'fj',
        'Finland' => 'fi',
        'France' => 'fr',
        'French Guiana' => 'gf',
        'French Polynesia' => 'pf',
        'French Southern Territories' => 'tf',

        // G
        'Gabon' => 'ga',
        'Gambia' => 'gm',
        'The Gambia' => 'gm',
        'Georgia' => 'ge',
        'Germany' => 'de',
        'Ghana' => 'gh',
        'Gibraltar' => 'gi',
        'Greece' => 'gr',
        'Greenland' => 'gl',
        'Grenada' => 'gd',
        'Guadeloupe' => 'gp',
        'Guam' => 'gu',
        'Guatemala' => 'gt',
        'Guernsey' => 'gg',
        'Guinea' => 'gn',
        'Guinea-Bissau' => 'gw',
        'Guyana' => 'gy',

        // H
        'Haiti' => 'ht',
        'Heard Island and McDonald Islands' => 'hm',
        'Holy See' => 'va',
        'Vatican' => 'va',
        'Vatican City' => 'va',
        'Honduras' => 'hn',
        'Hong Kong' => 'hk',
        'Hungary' => 'hu',

        // I
        'Iceland' => 'is',
        'India' => 'in',
        'Indonesia' => 'id',
        'Iran' => 'ir',
        'Iran, Islamic Republic of' => 'ir',
        'Iraq' => 'iq',
        'Ireland' => 'ie',
        'Republic of Ireland' => 'ie',
        'Isle of Man' => 'im',
        'Israel' => 'il',
        'Italy' => 'it',

        // J
        'Jamaica' => 'jm',
        'Japan' => 'jp',
        'Jersey' => 'je',
        'Jordan' => 'jo',

        // K
        'Kazakhstan' => 'kz',
        'Kenya' => 'ke',
        'Kiribati' => 'ki',
        'Korea, Democratic People\'s Republic of' => 'kp',
        'North Korea' => 'kp',
        'Korea, Republic of' => 'kr',
        'South Korea' => 'kr',
        'Korea Republic' => 'kr',
        'Kuwait' => 'kw',
        'Kyrgyzstan' => 'kg',

        // L
        'Laos' => 'la',
        'Lao People\'s Democratic Republic' => 'la',
        'Latvia' => 'lv',
        'Lebanon' => 'lb',
        'Lesotho' => 'ls',
        'Liberia' => 'lr',
        'Libya' => 'ly',
        'Liechtenstein' => 'li',
        'Lithuania' => 'lt',
        'Luxembourg' => 'lu',

        // M
        'Macao' => 'mo',
        'Macau' => 'mo',
        'Madagascar' => 'mg',
        'Malawi' => 'mw',
        'Malaysia' => 'my',
        'Maldives' => 'mv',
        'Mali' => 'ml',
        'Malta' => 'mt',
        'Marshall Islands' => 'mh',
        'Martinique' => 'mq',
        'Mauritania' => 'mr',
        'Mauritius' => 'mu',
        'Mayotte' => 'yt',
        'Mexico' => 'mx',
        'Micronesia' => 'fm',
        'Micronesia, Federated States of' => 'fm',
        'Moldova' => 'md',
        'Moldova, Republic of' => 'md',
        'Monaco' => 'mc',
        'Mongolia' => 'mn',
        'Montenegro' => 'me',
        'Montserrat' => 'ms',
        'Morocco' => 'ma',
        'Mozambique' => 'mz',
        'Myanmar' => 'mm',
        'Burma' => 'mm',

        // N
        'Namibia' => 'na',
        'Nauru' => 'nr',
        'Nepal' => 'np',
        'Netherlands' => 'nl',
        'Netherlands, Kingdom of the' => 'nl',
        'Holland' => 'nl',
        'New Caledonia' => 'nc',
        'New Zealand' => 'nz',
        'Nicaragua' => 'ni',
        'Niger' => 'ne',
        'Nigeria' => 'ng',
        'Niue' => 'nu',
        'Norfolk Island' => 'nf',
        'North Macedonia' => 'mk',
        'Macedonia' => 'mk',
        'Northern Mariana Islands' => 'mp',
        'Norway' => 'no',

        // O
        'Oman' => 'om',

        // P
        'Pakistan' => 'pk',
        'Palau' => 'pw',
        'Palestine' => 'ps',
        'Palestine, State of' => 'ps',
        'Panama' => 'pa',
        'Papua New Guinea' => 'pg',
        'Paraguay' => 'py',
        'Peru' => 'pe',
        'Philippines' => 'ph',
        'Pitcairn' => 'pn',
        'Poland' => 'pl',
        'Portugal' => 'pt',
        'Puerto Rico' => 'pr',

        // Q
        'Qatar' => 'qa',

        // R
        'Réunion' => 're',
        'Reunion' => 're',
        'Romania' => 'ro',
        'Russia' => 'ru',
        'Russian Federation' => 'ru',
        'Rwanda' => 'rw',

        // S
        'Saint Barthélemy' => 'bl',
        'Saint Helena, Ascension and Tristan da Cunha' => 'sh',
        'Saint Kitts and Nevis' => 'kn',
        'St. Kitts and Nevis' => 'kn',
        'Saint Lucia' => 'lc',
        'St. Lucia' => 'lc',
        'Saint Martin (French part)' => 'mf',
        'Saint Pierre and Miquelon' => 'pm',
        'Saint Vincent and the Grenadines' => 'vc',
        'St. Vincent and the Grenadines' => 'vc',
        'Samoa' => 'ws',
        'San Marino' => 'sm',
        'Sao Tome and Principe' => 'st',
        'São Tomé and Príncipe' => 'st',
        'Saudi Arabia' => 'sa',
        'Senegal' => 'sn',
        'Serbia' => 'rs',
        'Seychelles' => 'sc',
        'Sierra Leone' => 'sl',
        'Singapore' => 'sg',
        'Sint Maarten (Dutch part)' => 'sx',
        'Slovakia' => 'sk',
        'Slovenia' => 'si',
        'Solomon Islands' => 'sb',
        'Somalia' => 'so',
        'South Africa' => 'za',
        'South Georgia and the South Sandwich Islands' => 'gs',
        'South Sudan' => 'ss',
        'Spain' => 'es',
        'Sri Lanka' => 'lk',
        'Sudan' => 'sd',
        'Suriname' => 'sr',
        'Svalbard and Jan Mayen' => 'sj',
        'Sweden' => 'se',
        'Switzerland' => 'ch',
        'Syria' => 'sy',
        'Syrian Arab Republic' => 'sy',

        // T
        'Taiwan' => 'tw',
        'Taiwan, Province of China' => 'tw',
        'Chinese Taipei' => 'tw',
        'Tajikistan' => 'tj',
        'Tanzania' => 'tz',
        'Tanzania, United Republic of' => 'tz',
        'Thailand' => 'th',
        'Timor-Leste' => 'tl',
        'East Timor' => 'tl',
        'Togo' => 'tg',
        'Tokelau' => 'tk',
        'Tonga' => 'to',
        'Trinidad and Tobago' => 'tt',
        'Tunisia' => 'tn',
        'Turkey' => 'tr',
        'Türkiye' => 'tr',
        'Turkmenistan' => 'tm',
        'Turks and Caicos Islands' => 'tc',
        'Tuvalu' => 'tv',

        // U
        'Uganda' => 'ug',
        'Ukraine' => 'ua',
        'United Arab Emirates' => 'ae',
        'UAE' => 'ae',
        'United Kingdom' => 'gb',
        'United Kingdom of Great Britain and Northern Ireland' => 'gb',
        'Great Britain' => 'gb',
        'United States' => 'us',
        'United States of America' => 'us',
        'USA' => 'us',
        'United States Minor Outlying Islands' => 'um',
        'Uruguay' => 'uy',
        'Uzbekistan' => 'uz',

        // V
        'Vanuatu' => 'vu',
        'Venezuela' => 've',
        'Venezuela, Bolivarian Republic of' => 've',
        'Vietnam' => 'vn',
        'Viet Nam' => 'vn',
        'Virgin Islands (British)' => 'vg',
        'British Virgin Islands' => 'vg',
        'Virgin Islands (U.S.)' => 'vi',
        'U.S. Virgin Islands' => 'vi',

        // W
        'Wallis and Futuna' => 'wf',
        'Western Sahara' => 'eh',

        // Y
        'Yemen' => 'ye',

        // Z
        'Zambia' => 'zm',
        'Zimbabwe' => 'zw',

        // Football-specific: UK constituent countries (have separate football teams)
        'England' => 'gb-eng',
        'Scotland' => 'gb-sct',
        'Wales' => 'gb-wls',
        'Northern Ireland' => 'gb-nir',

        // Kosovo (not in ISO but recognized by FIFA)
        'Kosovo' => 'xk',
    ];

    /**
     * Get ISO code for a country name.
     */
    public static function toCode(string $countryName): ?string
    {
        return self::$countryToCode[$countryName] ?? null;
    }

    /**
     * Get ISO codes for multiple country names.
     *
     * @param array<string> $countryNames
     * @return array<array{name: string, code: string}>
     */
    public static function toCodes(array $countryNames): array
    {
        $result = [];

        foreach ($countryNames as $name) {
            $code = self::toCode($name);
            if ($code !== null) {
                $result[] = [
                    'name' => $name,
                    'code' => $code,
                ];
            }
        }

        return $result;
    }

    /**
     * Check if a country name is mapped.
     */
    public static function has(string $countryName): bool
    {
        return isset(self::$countryToCode[$countryName]);
    }

    /**
     * Get all mapped country names.
     *
     * @return array<string>
     */
    public static function getAllCountries(): array
    {
        return array_keys(self::$countryToCode);
    }
}
