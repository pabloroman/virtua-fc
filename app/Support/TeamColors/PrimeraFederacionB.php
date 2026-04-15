<?php

namespace App\Support\TeamColors;

final class PrimeraFederacionB implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
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
                'number' => 'black',
            ],
            'Villarreal CF B' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'yellow-400',
                'number' => 'blue-900',
            ],
            'FC Cartagena' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'red-600',
            ],
            'Gimnàstic de Tarragona' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'CE Sabadell FC' => [
                'pattern' => 'quarters',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'black',
            ],
            'Marbella FC' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-600',
                'number' => 'blue-600',
            ],
            'Sevilla Atlético' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'black',
            ],
            'Algeciras CF' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'blue-800',
            ],
            'UD Ibiza' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CD Eldense' => [
                'pattern' => 'stripes',
                'primary' => 'blue-800',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'AD Alcorcón' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Antequera CF' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'CD Teruel' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'blue-800',
                'number' => 'white',
            ],
            'Atlético Sanluqueño CF' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'CE Europa' => [
                'pattern' => 'chevron',
                'primary' => 'white',
                'secondary' => 'blue-600',
                'number' => 'blue-700',
            ],
            'Atlético Madrileño' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
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
                'primary' => 'red-600',
                'secondary' => 'blue-800',
                'number' => 'white',
            ],
            'Betis Deportivo Balompié' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
        ];
    }
}
