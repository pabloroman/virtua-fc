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
            'number' => 'purple-800',
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
            'number' => 'blue-700',
        ],
        'Athletic Bilbao' => [
            'pattern' => 'stripes',
            'primary' => 'red-600',
            'secondary' => 'white',
            'number' => 'black',
        ],
        'Real Betis Balompié' => [
            'pattern' => 'stripes',
            'primary' => 'green-600',
            'secondary' => 'white',
            'number' => 'white',
        ],
        'Real Sociedad' => [
            'pattern' => 'stripes',
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
            'number' => 'black',
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
            'primary' => 'blue-700',
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
            'primary' => 'red-700',
            'secondary' => 'black',
            'number' => 'black',
        ],
        'Rayo Vallecano' => [
            'pattern' => 'sash',
            'primary' => 'white',
            'secondary' => 'red-500',
            'number' => 'black',
        ],
        'Deportivo Alavés' => [
            'pattern' => 'stripes',
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
            'pattern' => 'stripes',
            'primary' => 'blue-900',
            'secondary' => 'rose-800',
            'number' => 'yellow-400',
        ],
        'Elche CF' => [
            'pattern' => 'hoops',
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
     * Get all teams with hex colors for preview/testing.
     */
    public static function all(): array
    {
        $result = [];
        foreach (self::TEAMS as $name => $colors) {
            $result[$name] = self::toHex($colors);
        }

        return $result;
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
