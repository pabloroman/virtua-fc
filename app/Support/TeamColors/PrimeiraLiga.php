<?php

namespace App\Support\TeamColors;

final class PrimeiraLiga implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'SL Benfica' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Porto' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Sporting CP' => [
                'pattern' => 'hoops',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'SC Braga' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Vitória Guimarães SC' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Moreirense FC' => [
                'pattern' => 'stripes',
                'primary' => 'green-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CD Santa Clara' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Famalicão' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],
            'Gil Vicente FC' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'blue-800',
                'number' => 'white',
            ],
            'GD Estoril Praia' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],
            'Rio Ave FC' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Casa Pia AC' => [
                'pattern' => 'solid',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Arouca' => [
                'pattern' => 'solid',
                'primary' => 'amber-400',
                'secondary' => 'blue-900',
                'number' => 'blue-900',
            ],
            'CD Nacional' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CF Estrela Amadora' => [
                'pattern' => 'solid',
                'primary' => 'green-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'AVS Futebol SAD' => [
                'pattern' => 'halves',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Alverca' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CD Tondela' => [
                'pattern' => 'solid',
                'primary' => 'green-700',
                'secondary' => 'yellow-400',
                'number' => 'yellow-400',
            ],

            'Académico Viseu FC' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Recently relegated / promotion candidates — kept so a season's
            // promoted side is not seeded with the default blue kit.
            'Boavista FC' => [
                'pattern' => 'quarters',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'SC Farense' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CS Marítimo' => [
                'pattern' => 'stripes',
                'primary' => 'green-700',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'Portimonense SC' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'SC União Torreense' => [
                'pattern' => 'solid',
                'primary' => 'green-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Leixões SC' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Vitória FC' => [
                'pattern' => 'solid',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
        ];
    }
}
