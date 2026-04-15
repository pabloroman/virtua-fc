<?php

namespace App\Support\TeamColors;

final class PrimeraFederacionB implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            // TODO: assign real kit colors
            'Real Murcia CF' => [
                'pattern' => 'solid',
                'primary' => 'red-800',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Hércules CF' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Villarreal CF B' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'FC Cartagena' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Gimnàstic de Tarragona' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CE Sabadell FC' => [
                'pattern' => 'halves',
                'primary' => 'blue-800',
                'secondary' => 'white',
                'number' => 'yellow-500',
            ],
            'Marbella FC' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],
            'Sevilla Atlético' => [
                'pattern' => 'sash',
                'primary' => 'white',
                'secondary' => 'red-700',
                'number' => 'black',
            ],
            'Algeciras CF' => [
                'pattern' => 'stripes',
                'primary' => 'red-700',
                'secondary' => 'white',
                'number' => 'blue-700',
            ],
            'UD Ibiza' => [
                'pattern' => 'solid',
                'primary' => 'sky-500',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CD Eldense' => [
                'pattern' => 'halves',
                'primary' => 'blue-800',
                'secondary' => 'red-800',
                'number' => 'white',
            ],
            'AD Alcorcón' => [
                'pattern' => 'solid',
                'primary' => 'yellow-500',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],
            'Antequera CF' => [
                'pattern' => 'stripes',
                'primary' => 'green-500',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'CD Teruel' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'blue-600',
                'number' => 'white',
            ],
            'Atlético Sanluqueño CF' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'CE Europa' => [
                'pattern' => 'bar',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'Atlético Madrileño' => [
                'pattern' => 'stripes',
                'primary' => 'red-700',
                'secondary' => 'white',
                'number' => 'blue-700',
            ],
            'Juventud Torremolinos CF' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'SD Tarazona' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Betis Deportivo Balompié' => [
                'pattern' => 'solid',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
        ];
    }
}
