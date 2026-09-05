<?php

namespace App\Support\TeamColors;

final class Eredivisie implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'Ajax Amsterdam' => [
                'pattern' => 'bar',
                'primary' => 'white',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'PSV Eindhoven' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Feyenoord Rotterdam' => [
                'pattern' => 'halves',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Utrecht' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'AZ Alkmaar' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Twente Enschede' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Go Ahead Eagles' => [
                'pattern' => 'halves',
                'primary' => 'red-600',
                'secondary' => 'yellow-400',
                'number' => 'white',
            ],
            'NEC Nijmegen' => [
                'pattern' => 'halves',
                'primary' => 'red-600',
                'secondary' => 'green-700',
                'number' => 'white',
            ],
            'Sparta Rotterdam' => [
                'pattern' => 'halves',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Fortuna Sittard' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'green-700',
                'number' => 'green-700',
            ],
            'Heracles Almelo' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'PEC Zwolle' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'SC Heerenveen' => [
                'pattern' => 'halves',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'FC Groningen' => [
                'pattern' => 'halves',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'NAC Breda' => [
                'pattern' => 'halves',
                'primary' => 'yellow-400',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Excelsior Rotterdam' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'SC Telstar' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'FC Volendam' => [
                'pattern' => 'solid',
                'primary' => 'orange-500',
                'secondary' => 'black',
                'number' => 'black',
            ],

            'ADO Den Haag' => [
                'pattern' => 'halves',
                'primary' => 'green-600',
                'secondary' => 'yellow-400',
                'number' => 'white',
            ],
            'SC Cambuur Leeuwarden' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'yellow-400',
                'number' => 'white',
            ],

            // Recently relegated / promotion candidates — kept so a season's
            // promoted side is not seeded with the default blue kit.
            'Willem II Tilburg' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'RKC Waalwijk' => [
                'pattern' => 'stripes',
                'primary' => 'yellow-400',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],
            'Almere City FC' => [
                'pattern' => 'solid',
                'primary' => 'black',
                'secondary' => 'red-600',
                'number' => 'red-600',
            ],
            'Vitesse Arnhem' => [
                'pattern' => 'halves',
                'primary' => 'yellow-400',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'FC Den Bosch' => [
                'pattern' => 'solid',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'De Graafschap' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
        ];
    }
}
