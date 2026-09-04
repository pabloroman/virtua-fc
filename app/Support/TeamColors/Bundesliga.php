<?php

namespace App\Support\TeamColors;

final class Bundesliga implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'Bayern Munich' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Borussia Dortmund' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Bayer 04 Leverkusen' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'RB Leipzig' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'Eintracht Frankfurt' => [
                'pattern' => 'solid',
                'primary' => 'black',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'VfB Stuttgart' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'SC Freiburg' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'Borussia Mönchengladbach' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'green-600',
                'number' => 'green-600',
            ],
            'SV Werder Bremen' => [
                'pattern' => 'solid',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'VfL Wolfsburg' => [
                'pattern' => 'solid',
                'primary' => 'green-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            '1.FC Köln' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'Hamburger SV' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            '1.FC Union Berlin' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            '1.FSV Mainz 05' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'TSG 1899 Hoffenheim' => [
                'pattern' => 'solid',
                'primary' => 'blue-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Augsburg' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'green-600',
            ],
            'FC St. Pauli' => [
                'pattern' => 'solid',
                'primary' => 'amber-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            '1.FC Heidenheim 1846' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'blue-700',
                'number' => 'white',
            ],
            'FC Schalke 04' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'SC Paderborn 07' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'SV 07 Elversberg' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],
        ];
    }
}
