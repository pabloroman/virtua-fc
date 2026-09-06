<?php

namespace App\Support\TeamColors;

final class LaLiga2 implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'Deportivo A Coruña' => [
                'pattern' => 'stripes',
                'primary' => 'blue-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'UD Las Palmas' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'yellow-400',
                'number' => 'blue-900',
            ],
            'Málaga CF' => [
                'pattern' => 'stripes',
                'primary' => 'blue-500',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Sporting Gijón' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'Real Valladolid CF' => [
                'pattern' => 'stripes',
                'primary' => 'purple-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Racing Santander' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'green-700',
            ],
            'Córdoba CF' => [
                'pattern' => 'stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'green-600',
            ],
            'Cádiz CF' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'Real Zaragoza' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'Granada CF' => [
                'pattern' => 'hoops',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'red-600',
            ],
            'UD Almería' => [
                'pattern' => 'stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Albacete Balompié' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'CD Castellón' => [
                'pattern' => 'stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'Cultural Leonesa' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'red-700',
                'number' => 'red-700',
            ],
            'CD Leganés' => [
                'pattern' => 'stripes',
                'primary' => 'blue-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Burgos CF' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'SD Huesca' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'red-600',
                'number' => 'white',
            ],
            'SD Eibar' => [
                'pattern' => 'stripes',
                'primary' => 'blue-900',
                'secondary' => 'rose-800',
                'number' => 'white',
            ],
            'AD Ceuta FC' => [
                'pattern' => 'solid',
                'primary' => 'white',
                'secondary' => 'black',
                'number' => 'black',
            ],
            'CD Mirandés' => [
                'pattern' => 'solid',
                'primary' => 'red-700',
                'secondary' => 'red-700',
                'number' => 'black',
            ],
            'FC Andorra' => [
                'pattern' => 'solid',
                'primary' => 'blue-800',
                'secondary' => 'yellow-400',
                'number' => 'yellow-400',
            ],
            'Real Sociedad B' => [
                'pattern' => 'stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'white',
            ],
        ];
    }
}
