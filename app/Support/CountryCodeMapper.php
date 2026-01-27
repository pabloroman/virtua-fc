<?php

namespace App\Support;

class CountryCodeMapper
{
    private static array $countryToCode = [
        'Spain' => 'es', 'Germany' => 'de', 'France' => 'fr', 'Italy' => 'it', 'England' => 'gb-eng',
        'Portugal' => 'pt', 'Brazil' => 'br', 'Argentina' => 'ar', 'Netherlands' => 'nl', 'Belgium' => 'be',
        'Croatia' => 'hr', 'Uruguay' => 'uy', 'Colombia' => 'co', 'Mexico' => 'mx', 'Chile' => 'cl',
        'Morocco' => 'ma', 'Senegal' => 'sn', 'Nigeria' => 'ng', 'Ghana' => 'gh', 'Cameroon' => 'cm',
        'Japan' => 'jp', 'South Korea' => 'kr', 'Australia' => 'au', 'United States' => 'us', 'Canada' => 'ca',
        'Austria' => 'at', 'Switzerland' => 'ch', 'Poland' => 'pl', 'Ukraine' => 'ua', 'Serbia' => 'rs',
        'Denmark' => 'dk', 'Sweden' => 'se', 'Norway' => 'no', 'Finland' => 'fi', 'Iceland' => 'is',
        'Scotland' => 'gb-sct', 'Wales' => 'gb-wls', 'Ireland' => 'ie', 'Northern Ireland' => 'gb-nir',
        'Czech Republic' => 'cz', 'Czechia' => 'cz', 'Slovakia' => 'sk', 'Hungary' => 'hu', 'Romania' => 'ro',
        'Bulgaria' => 'bg', 'Greece' => 'gr', 'Turkey' => 'tr', 'Russia' => 'ru', 'Slovenia' => 'si',
        'Bosnia and Herzegovina' => 'ba', 'Bosnia-Herzegovina' => 'ba', 'Montenegro' => 'me', 'Albania' => 'al',
        'North Macedonia' => 'mk', 'Kosovo' => 'xk', 'Paraguay' => 'py', 'Peru' => 'pe', 'Ecuador' => 'ec',
        'Venezuela' => 've', 'Bolivia' => 'bo', 'Costa Rica' => 'cr', 'Honduras' => 'hn', 'Panama' => 'pa',
        'Jamaica' => 'jm', 'Ivory Coast' => 'ci', 'Côte d\'Ivoire' => 'ci', 'Mali' => 'ml', 'Guinea' => 'gn',
        'DR Congo' => 'cd', 'Congo' => 'cg', 'Egypt' => 'eg', 'Tunisia' => 'tn', 'Algeria' => 'dz',
        'South Africa' => 'za', 'China' => 'cn', 'Iran' => 'ir', 'Saudi Arabia' => 'sa', 'Israel' => 'il',
        'Georgia' => 'ge', 'Armenia' => 'am', 'Azerbaijan' => 'az', 'Kazakhstan' => 'kz', 'Uzbekistan' => 'uz',
        'Sierra Leone' => 'sl', 'Cape Verde' => 'cv', 'Equatorial Guinea' => 'gq', 'Gabon' => 'ga',
        'Burkina Faso' => 'bf', 'Benin' => 'bj', 'Togo' => 'tg', 'Gambia' => 'gm', 'Mauritania' => 'mr',
        'Angola' => 'ao', 'Mozambique' => 'mz', 'Zambia' => 'zm', 'Zimbabwe' => 'zw', 'Madagascar' => 'mg',
        'New Zealand' => 'nz', 'Philippines' => 'ph', 'Indonesia' => 'id', 'Thailand' => 'th', 'Vietnam' => 'vn',
        'Luxembourg' => 'lu', 'Cyprus' => 'cy', 'Malta' => 'mt', 'Estonia' => 'ee', 'Latvia' => 'lv',
        'Lithuania' => 'lt', 'Belarus' => 'by', 'Moldova' => 'md', 'Dominican Republic' => 'do', 'Cuba' => 'cu',
        'Curaçao' => 'cw', 'Curacao' => 'cw', 'Haiti' => 'ht', 'El Salvador' => 'sv', 'Guatemala' => 'gt',
        'Nicaragua' => 'ni', 'Trinidad and Tobago' => 'tt',
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
}
