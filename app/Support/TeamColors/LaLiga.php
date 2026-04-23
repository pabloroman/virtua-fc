<?php

namespace App\Support\TeamColors;

final class LaLiga implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'Real Madrid' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'white',
                'number' => 'purple-800',
            ],
            'FC Barcelona' => [
                'pattern' => 'stripes',
                'primary' => 'rose-800',
                'secondary' => 'blue-800',
                'number' => 'white',
            ],
            'Atlético de Madrid' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'blue-700',
            ],
            'Athletic Club' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'Real Betis Balompié' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Real Sociedad' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Sevilla FC' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'Valencia CF' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'orange-500',
                'number' => 'black',
            ],
            'Villarreal CF' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'yellow-400',
                'number' => 'blue-900',
            ],
            'RC Celta' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'sky-400',
                'number' => 'white',
            ],
            'RCD Espanyol Barcelona' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CA Osasuna' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'blue-900',
                'number' => 'white',
            ],
            'Getafe CF' => [
                'pattern' => 'solid',
                'primary' => 'blue-600',
                'secondary' => 'blue-600',
                'number' => 'white',
            ],
            'RCD Mallorca' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Rayo Vallecano' => [
                'pattern' => 'sash',
                'primary' => 'white',
                'secondary' => 'red-500',
                'number' => 'black',
            ],
            'Deportivo Alavés' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Girona FC' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'red-600',
            ],
            'Levante UD' => [
                'pattern' => 'stripes',
                'primary' => 'blue-900',
                'secondary' => 'rose-800',
                'number' => 'yellow-400',
            ],
            'Elche CF' => [
                'pattern' => 'hoops',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Real Oviedo' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'blue-700',
                'number' => 'white',
            ],
        ];
    }
}
