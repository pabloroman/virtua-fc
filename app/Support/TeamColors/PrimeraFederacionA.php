<?php

namespace App\Support\TeamColors;

final class PrimeraFederacionA implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'CD Tenerife' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'Mérida AD' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Racing Ferrol' => [
                'pattern' => 'solid',
                'primary' => 'green-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Pontevedra CF' => [
                'pattern' => 'solid',
                'primary' => 'rose-800',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Real Madrid Castilla' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'white',
                'number' => 'purple-800',
            ],
            'SD Ponferradina' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'amber-400',
            ],
            'Barakaldo CF' => [
                'pattern' => 'stripes',
                'primary' => 'yellow-400',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'Zamora CF' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'CD Lugo' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'CD Guadalajara' => [
                'pattern' => 'solid',
                'primary' => 'purple-800',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CP Cacereño' => [
                'pattern' => 'solid',
                'primary' => 'emerald-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Ourense CF' => [
                'pattern' => 'solid',
                'primary' => 'blue-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Real Avilés Industrial' => [
                'pattern' => 'stripes',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'black',
            ],
            'Unionistas CF' => [
                'pattern' => 'halves',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CD Arenteiro' => [
                'pattern' => 'solid',
                'primary' => 'emerald-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CF Talavera de la Reina' => [
                'pattern' => 'stripes',
                'primary' => 'white',
                'secondary' => 'blue-600',
                'number' => 'black',
            ],
            'CA Osasuna Promesas' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'blue-900',
                'number' => 'white',
            ],
            'Bilbao Athletic' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'Arenas Club' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'black',
                'number' => 'white',
            ],
            'RC Celta Fortuna' => [
                'pattern' => 'solid',
                'primary' => 'sky-400',
                'secondary' => 'sky-400',
                'number' => 'white',
            ],
        ];
    }
}
