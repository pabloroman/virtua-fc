<?php

namespace App\Support\TeamColors;

final class SerieA implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'Inter Milan' => [
                'pattern' => 'stripes',
                'primary' => 'blue-900',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'Juventus FC' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'AC Milan' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'SSC Napoli' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'sky-400',
                'number' => 'white',
            ],
            'Atalanta BC' => [
                'pattern' => 'stripes',
                'primary' => 'blue-800',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'AS Roma' => [
                'pattern' => 'solid',
                'primary' => 'red-800',
                'secondary' => 'amber-500',
                'number' => 'amber-500',
            ],
            'SS Lazio' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'ACF Fiorentina' => [
                'pattern' => 'solid',
                'primary' => 'purple-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Bologna FC 1909' => [
                'pattern' => 'stripes',
                'primary' => 'red-700',
                'secondary' => 'blue-900',
                'number' => 'white',
            ],
            'Torino FC' => [
                'pattern' => 'solid',
                'primary' => 'red-800',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Genoa CFC' => [
                'pattern' => 'halves',
                'primary' => 'red-700',
                'secondary' => 'blue-900',
                'number' => 'white',
            ],
            'Udinese Calcio' => [
                'pattern' => 'stripes',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'US Lecce' => [
                'pattern' => 'stripes',
                'primary' => 'yellow-400',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'Parma Calcio 1913' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'Cagliari Calcio' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'blue-800',
                'number' => 'white',
            ],
            'Hellas Verona' => [
                'pattern' => 'stripes',
                'primary' => 'yellow-400',
                'secondary' => 'blue-800',
                'number' => 'white',
            ],
            'US Sassuolo' => [
                'pattern' => 'stripes',
                'primary' => 'green-700',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'Como 1907' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'US Cremonese' => [
                'pattern' => 'stripes',
                'primary' => 'red-700',
                'secondary' => 'gray-900',
                'number' => 'white',
            ],
            'Pisa Sporting Club' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'AC Monza' => [
                'pattern' => 'halves',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Frosinone Calcio' => [
                'pattern' => 'stripes',
                'primary' => 'yellow-400',
                'secondary' => 'blue-700',
                'number' => 'white',
            ],
            'Venezia FC' => [
                'pattern' => 'solid',
                'primary' => 'black',
                'secondary' => 'orange-500',
                'number' => 'white',
            ],
        ];
    }
}
