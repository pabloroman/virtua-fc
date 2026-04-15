<?php

namespace App\Support\TeamColors;

final class PrimeraFederacionB implements TeamColorProvider
{
    public static function teams(): array
    {
        return [
            'Real Murcia CF' => [
                'pattern' => 'solid',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'white',
            ],
            'Hércules CF' => [
                'pattern' => 'vertical-stripes',
                'primary' => 'blue-700',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'Villarreal CF B' => [
                'pattern' => 'solid',
                'primary' => 'yellow-400',
                'secondary' => 'blue-800',
                'number' => 'blue-800',
            ],
            'FC Cartagena' => [
                'pattern' => 'vertical-stripes',
                'primary' => 'black',
                'secondary' => 'white',
                'number' => 'red-600',
            ],
            'Gimnàstic de Tarragona' => [
                'pattern' => 'solid',
                'primary' => 'red-700', // Grana
                'secondary' => 'black',
                'number' => 'white',
            ],
            'CE Sabadell FC' => [
                'pattern' => 'quarters', // Arlequinado
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
                'pattern' => 'vertical-stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'blue-800',
            ],
            'UD Ibiza' => [
                'pattern' => 'solid',
                'primary' => 'sky-400', // Celeste
                'secondary' => 'white',
                'number' => 'white',
            ],
            'CD Eldense' => [
                'pattern' => 'vertical-stripes',
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
                'pattern' => 'vertical-stripes',
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
                'pattern' => 'vertical-stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
            'CE Europa' => [
                'pattern' => 'solid', // Originalmente tiene un escapulario (V), usamos base blanca con toques azules
                'primary' => 'white',
                'secondary' => 'blue-700',
                'number' => 'blue-700',
            ],
            'Atlético Madrileño' => [ // Atlético de Madrid B
                'pattern' => 'vertical-stripes',
                'primary' => 'red-600',
                'secondary' => 'white',
                'number' => 'blue-800',
            ],
            'Juventud Torremolinos CF' => [
                'pattern' => 'vertical-stripes',
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
                'pattern' => 'vertical-stripes',
                'primary' => 'green-600',
                'secondary' => 'white',
                'number' => 'black',
            ],
        ];
    }
}
