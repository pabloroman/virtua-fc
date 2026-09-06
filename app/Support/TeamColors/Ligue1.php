<?php

namespace App\Support\TeamColors;

final class Ligue1 implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'Paris Saint-Germain' => [
                'pattern' => 'bar',
                'primary' => 'blue-900',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'Olympique Marseille' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'sky-400',
                'number' => 'sky-400',
            ],
            'AS Monaco' => [
                'pattern' => 'halves',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Olympique Lyon' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'LOSC Lille' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'blue-900',
                'number' => 'white',
            ],
            'OGC Nice' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'Stade Rennais FC' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'RC Lens' => [
                'pattern' => 'stripes',
                'primary' => 'yellow-400',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'FC Nantes' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'green-600',
                'number' => 'green-600',
            ],
            'FC Toulouse' => [
                'pattern' => 'solid',
                'primary' => 'purple-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'RC Strasbourg Alsace' => [
                'pattern' => 'solid',
                'primary' => 'blue-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Metz' => [
                'pattern' => 'solid',
                'primary' => 'red-800',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Le Havre AC' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'blue-900',
                'number' => 'white',
            ],
            'Stade Brestois 29' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'AJ Auxerre' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-600',
                'number' => 'blue-600',
            ],
            'Angers SCO' => [
                'pattern' => 'solid',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Paris FC' => [
                'pattern' => 'solid',
                'primary' => 'blue-800',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'FC Lorient' => [
                'pattern' => 'solid',
                'primary' => 'orange-500',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'ESTAC Troyes' => [
                'pattern' => 'halves',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Le Mans FC' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'yellow-400',
                'number' => 'white',
            ],
        ];
    }
}
