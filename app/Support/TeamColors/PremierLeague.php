<?php

namespace App\Support\TeamColors;

final class PremierLeague implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'Manchester City' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Liverpool FC' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'Arsenal FC' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Chelsea FC' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'blue-700',
                'number' => 'white',
            ],
            'Manchester United' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Tottenham Hotspur' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-900',
                'number' => 'blue-900',
            ],
            'Newcastle United' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Aston Villa' => [
                'pattern' => 'solid',
                'primary' => 'purple-800',
                'secondary' => 'sky-400',
                'number' => 'sky-400',
            ],
            'West Ham United' => [
                'pattern' => 'solid',
                'primary' => 'red-800',
                'secondary' => 'sky-400',
                'number' => 'sky-400',
            ],
            'Nottingham Forest' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Everton FC' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Brighton & Hove Albion' => [
                'pattern' => 'stripes',
                'primary' => 'blue-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Crystal Palace' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'blue-700',
                'number' => 'white',
            ],
            'Wolverhampton Wanderers' => [
                'pattern' => 'solid',
                'primary' => 'amber-500',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Leeds United' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'white',
                'number' => 'blue-700',
            ],
            'Fulham FC' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Brentford FC' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'AFC Bournemouth' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'Sunderland AFC' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Burnley FC' => [
                'pattern' => 'solid',
                'primary' => 'red-800',
                'secondary' => 'sky-400',
                'number' => 'sky-400',
            ],
            'Coventry City' => [
                'pattern' => 'solid',
                'primary' => 'sky-500',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Hull City' => [
                'pattern' => 'stripes',
                'primary' => 'amber-500',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'Ipswich Town' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
        ];
    }
}
