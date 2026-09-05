<?php

namespace App\Support\TeamColors;

final class EuropeanTransferPool implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            // Turkey
            'Galatasaray' => [
                'pattern' => 'halves',
                'primary' => 'yellow-400',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'Fenerbahce' => [
                'pattern' => 'stripes',
                'primary' => 'yellow-400',
                'secondary' => 'blue-900',
                'number' => 'blue-900',
            ],

            // Scotland
            'Celtic FC' => [
                'pattern' => 'hoops',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Rangers FC' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Austria
            'Red Bull Salzburg' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'SK Sturm Graz' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Belgium
            'Club Brugge KV' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'KRC Genk' => [
                'pattern' => 'solid',
                'primary' => 'blue-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Union Saint-Gilloise' => [
                'pattern' => 'stripes',
                'primary' => 'yellow-400',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],

            // Greece
            'Olympiacos Piraeus' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'PAOK Thessaloniki' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Panathinaikos FC' => [
                'pattern' => 'solid',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Denmark
            'FC Copenhagen' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'FC Midtjylland' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],

            // Switzerland
            'FC Basel 1893' => [
                'pattern' => 'halves',
                'primary' => 'red-600',
                'secondary' => 'blue-700',
                'number' => 'white',
            ],
            'BSC Young Boys' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'black',
                'number' => 'black',
            ],

            // Serbia
            'Red Star Belgrade' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Czech Republic
            'SK Slavia Prague' => [
                'pattern' => 'halves',
                'primary' => 'red-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Viktoria Plzen' => [
                'pattern' => 'stripes',
                'primary' => 'red-700',
                'secondary' => 'blue-800',
                'number' => 'white',
            ],

            // Hungary
            'Ferencvárosi TC' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Croatia
            'GNK Dinamo Zagreb' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Sweden
            'Malmö FF' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Norway
            'FK Bodø/Glimt' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'SK Brann' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Romania
            'FCSB' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'blue-700',
                'number' => 'white',
            ],

            // Bulgaria
            'Ludogorets Razgrad' => [
                'pattern' => 'solid',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Israel
            'Maccabi Tel Aviv' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],

            // Azerbaijan
            'Qarabağ FK' => [
                'pattern' => 'solid',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],

            // Cyprus
            'Pafos FC' => [
                'pattern' => 'solid',
                'primary' => 'blue-600',
                'secondary' => 'yellow-400',
                'number' => 'yellow-400',
            ],

            // Kazakhstan
            'Kairat Almaty' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'black',
                'number' => 'black',
            ],
        ];
    }
}
