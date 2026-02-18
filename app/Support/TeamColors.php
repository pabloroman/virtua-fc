<?php

namespace App\Support;

class TeamColors
{
    /**
     * Tailwind color name → hex value lookup.
     * Only includes shades actually used by teams.
     */
    private const TAILWIND_HEX = [
        'white' => '#FFFFFF',
        'black' => '#000000',
        'slate-900' => '#0F172A',
        'gray-900' => '#111827',
        'red-500' => '#EF4444',
        'red-600' => '#DC2626',
        'red-700' => '#B91C1C',
        'red-800' => '#991B1B',
        'rose-800' => '#9F1239',
        'orange-500' => '#F97316',
        'amber-400' => '#FBBF24',
        'amber-500' => '#F59E0B',
        'amber-600' => '#D97706',
        'yellow-400' => '#FACC15',
        'yellow-500' => '#EAB308',
        'lime-500' => '#84CC16',
        'green-600' => '#16A34A',
        'green-700' => '#15803D',
        'emerald-600' => '#059669',
        'sky-400' => '#38BDF8',
        'sky-500' => '#0EA5E9',
        'blue-500' => '#3B82F6',
        'blue-600' => '#2563EB',
        'blue-700' => '#1D4ED8',
        'blue-800' => '#1E40AF',
        'blue-900' => '#1E3A8A',
        'indigo-600' => '#4F46E5',
        'purple-600' => '#9333EA',
        'purple-700' => '#7E22CE',
        'purple-800' => '#6B21A8',
    ];

    /**
     * Team name → kit colors mapping.
     * Uses Tailwind color names for readability.
     *
     * Patterns: solid, stripes, hoops, sash, halves
     */
    private const TEAMS = [
        // La Liga
        'Real Madrid' => [
            'pattern' => 'solid',
            'primary' => 'white',
            'secondary' => 'white',
            'number' => 'slate-900',
        ],
        'FC Barcelona' => [
            'pattern' => 'stripes',
            'primary' => 'rose-800',
            'secondary' => 'blue-800',
            'number' => 'white',
        ],
        'Atlético de Madrid' => [
            'pattern' => 'stripes',
            'primary' => 'red-600',
            'secondary' => 'white',
            'number' => 'blue-900',
        ],
        'Athletic Bilbao' => [
            'pattern' => 'stripes',
            'primary' => 'red-600',
            'secondary' => 'white',
            'number' => 'slate-900',
        ],
        'Real Betis Balompié' => [
            'pattern' => 'stripes',
            'primary' => 'green-600',
            'secondary' => 'white',
            'number' => 'slate-900',
        ],
        'Real Sociedad' => [
            'pattern' => 'hoops',
            'primary' => 'blue-700',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'Sevilla FC' => [
            'pattern' => 'solid',
            'primary' => 'white',
            'secondary' => 'red-600',
            'number' => 'red-600',
        ],
        'Valencia CF' => [
            'pattern' => 'solid',
            'primary' => 'white',
            'secondary' => 'orange-500',
            'number' => 'slate-900',
        ],
        'Villarreal CF' => [
            'pattern' => 'solid',
            'primary' => 'yellow-400',
            'secondary' => 'yellow-400',
            'number' => 'blue-900',
        ],
        'Celta de Vigo' => [
            'pattern' => 'solid',
            'primary' => 'sky-400',
            'secondary' => 'sky-400',
            'number' => 'white',
        ],
        'RCD Espanyol Barcelona' => [
            'pattern' => 'stripes',
            'primary' => 'blue-600',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'CA Osasuna' => [
            'pattern' => 'solid',
            'primary' => 'red-700',
            'secondary' => 'blue-900',
            'number' => 'white',
        ],
        'Getafe CF' => [
            'pattern' => 'solid',
            'primary' => 'blue-600',
            'secondary' => 'blue-600',
            'number' => 'white',
        ],
        'RCD Mallorca' => [
            'pattern' => 'solid',
            'primary' => 'red-600',
            'secondary' => 'black',
            'number' => 'white',
        ],
        'Rayo Vallecano' => [
            'pattern' => 'sash',
            'primary' => 'white',
            'secondary' => 'red-500',
            'number' => 'slate-900',
        ],
        'Deportivo Alavés' => [
            'pattern' => 'solid',
            'primary' => 'blue-700',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'Girona FC' => [
            'pattern' => 'stripes',
            'primary' => 'red-600',
            'secondary' => 'white',
            'number' => 'red-600',
        ],
        'Levante UD' => [
            'pattern' => 'halves',
            'primary' => 'blue-700',
            'secondary' => 'red-600',
            'number' => 'white',
        ],
        'Elche CF' => [
            'pattern' => 'stripes',
            'primary' => 'green-600',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'Real Oviedo' => [
            'pattern' => 'solid',
            'primary' => 'blue-700',
            'secondary' => 'blue-700',
            'number' => 'white',
        ],

        // Segunda División
        'Deportivo de La Coruña' => [
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
            'number' => 'slate-900',
        ],
        'Real Valladolid CF' => [
            'pattern' => 'halves',
            'primary' => 'purple-700',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'Racing Santander' => [
            'pattern' => 'stripes',
            'primary' => 'green-700',
            'secondary' => 'black',
            'number' => 'white',
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
            'number' => 'blue-900',
        ],
        'Real Zaragoza' => [
            'pattern' => 'solid',
            'primary' => 'white',
            'secondary' => 'blue-700',
            'number' => 'blue-700',
        ],
        'Granada CF' => [
            'pattern' => 'stripes',
            'primary' => 'red-600',
            'secondary' => 'white',
            'number' => 'red-600',
        ],
        'UD Almería' => [
            'pattern' => 'solid',
            'primary' => 'red-600',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'Albacete Balompié' => [
            'pattern' => 'solid',
            'primary' => 'white',
            'secondary' => 'white',
            'number' => 'slate-900',
        ],
        'CD Castellón' => [
            'pattern' => 'solid',
            'primary' => 'black',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'Cultural Leonesa' => [
            'pattern' => 'solid',
            'primary' => 'white',
            'secondary' => 'green-600',
            'number' => 'slate-900',
        ],
        'CD Leganés' => [
            'pattern' => 'stripes',
            'primary' => 'blue-600',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'Burgos CF' => [
            'pattern' => 'solid',
            'primary' => 'black',
            'secondary' => 'purple-600',
            'number' => 'white',
        ],
        'SD Huesca' => [
            'pattern' => 'stripes',
            'primary' => 'blue-700',
            'secondary' => 'red-600',
            'number' => 'white',
        ],
        'SD Eibar' => [
            'pattern' => 'stripes',
            'primary' => 'blue-700',
            'secondary' => 'red-600',
            'number' => 'white',
        ],
        'AD Ceuta FC' => [
            'pattern' => 'solid',
            'primary' => 'green-600',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'CD Mirandés' => [
            'pattern' => 'solid',
            'primary' => 'red-600',
            'secondary' => 'red-600',
            'number' => 'white',
        ],
        'FC Andorra' => [
            'pattern' => 'solid',
            'primary' => 'blue-600',
            'secondary' => 'yellow-400',
            'number' => 'white',
        ],
        'Real Sociedad B' => [
            'pattern' => 'hoops',
            'primary' => 'blue-700',
            'secondary' => 'white',
            'number' => 'white',
        ],
    ];

    private const DEFAULT_COLORS = [
        'pattern' => 'solid',
        'primary' => 'blue-600',
        'secondary' => 'white',
        'number' => 'white',
    ];

    /**
     * Get raw color config for a team (Tailwind names).
     * Used for DB storage.
     */
    public static function get(string $teamName): array
    {
        return self::TEAMS[$teamName] ?? self::DEFAULT_COLORS;
    }

    /**
     * Get color config with hex values for JavaScript rendering.
     */
    public static function toHex(array $colors): array
    {
        return [
            'pattern' => $colors['pattern'] ?? 'solid',
            'primary' => self::resolveHex($colors['primary'] ?? 'blue-600'),
            'secondary' => self::resolveHex($colors['secondary'] ?? 'white'),
            'number' => self::resolveHex($colors['number'] ?? 'white'),
        ];
    }

    /**
     * Convert a Tailwind color name to hex.
     */
    private static function resolveHex(string $color): string
    {
        return self::TAILWIND_HEX[$color] ?? '#6B7280';
    }
}
