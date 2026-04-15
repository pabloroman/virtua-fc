<?php

namespace App\Support\TeamColors;

final class WorldCup implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            // Group A
            'Mexico' => [
                'pattern' => 'solid',
                'primary' => 'green-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'South Africa' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'green-700',
                'number' => 'green-700',
            ],
            'South Korea' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'Czechia' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],

            // Group B
            'Canada' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Qatar' => [
                'pattern' => 'solid',
                'primary' => 'red-900',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Bosnia-Herzegovina' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Switzerland' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Group C
            'Brazil' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'green-600',
                'number' => 'green-700',
            ],
            'Morocco' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'green-600',
                'number' => 'white',
            ],
            'Haiti' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'Scotland' => [
                'pattern' => 'solid',
                'primary' => 'blue-900',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Group D
            'United States' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-900',
                'number' => 'blue-900',
            ],
            'Paraguay' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'blue-700',
            ],
            'Australia' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'green-700',
                'number' => 'green-700',
            ],
            'Türkiye' => [
                'pattern' => 'bar',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],

            // Group E
            'Germany' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Curaçao' => [
                'pattern' => 'solid',
                'primary' => 'blue-600',
                'secondary' => 'yellow-400',
                'number' => 'white',
            ],
            'Ivory Coast' => [
                'pattern' => 'solid',
                'primary' => 'orange-600',
                'secondary' => 'green-600',
                'number' => 'white',
            ],
            'Ecuador' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],

            // Group F
            'Netherlands' => [
                'pattern' => 'solid',
                'primary' => 'orange-500',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'Japan' => [
                'pattern' => 'solid',
                'primary' => 'blue-800',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Sweden' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],
            'Tunisia' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Group G
            'Belgium' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'yellow-400',
                'number' => 'yellow-400',
            ],
            'Egypt' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Iran' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'New Zealand' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'black',
            ],

            // Group H
            'Spain' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'yellow-400',
                'number' => 'yellow-400',
            ],
            'Cape Verde' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'Saudi Arabia' => [
                'pattern' => 'solid',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Uruguay' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'white',
                'number' => 'black',
            ],

            // Group I
            'France' => [
                'pattern' => 'solid',
                'primary' => 'blue-900',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Senegal' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'green-600',
                'number' => 'green-600',
            ],
            'Iraq' => [
                'pattern' => 'solid',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Norway' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'blue-900',
            ],

            // Group J
            'Argentina' => [
                'pattern' => 'stripes',
                'primary' => 'sky-400',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'Algeria' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'green-600',
                'number' => 'green-600',
            ],
            'Austria' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Jordan' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],

            // Group K
            'Portugal' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'green-700',
                'number' => 'yellow-400',
            ],
            'DR Congo' => [
                'pattern' => 'solid',
                'primary' => 'blue-600',
                'secondary' => 'yellow-400',
                'number' => 'yellow-400',
            ],
            'Uzbekistan' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-600',
                'number' => 'blue-600',
            ],
            'Colombia' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],

            // Group L
            'England' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-900',
                'number' => 'blue-900',
            ],
            'Croatia' => [
                'pattern' => 'quarters',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'blue-700',
            ],
            'Ghana' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'yellow-400',
                'number' => 'black',
            ],
            'Panama' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
        ];
    }
}
