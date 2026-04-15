<?php

namespace App\Support;

use App\Support\TeamColors\Bundesliga;
use App\Support\TeamColors\EuropeanTransferPool;
use App\Support\TeamColors\LaLiga;
use App\Support\TeamColors\Ligue1;
use App\Support\TeamColors\PremierLeague;
use App\Support\TeamColors\PrimeraFederacionA;
use App\Support\TeamColors\PrimeraFederacionB;
use App\Support\TeamColors\LaLiga2;
use App\Support\TeamColors\SerieA;
use App\Support\TeamColors\WorldCup;

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
        'red-900' => '#7F1D1D',
        'emerald-700' => '#047857',
        'teal-700' => '#0F766E',
        'sky-600' => '#0284C7',
        'indigo-700' => '#4338CA',
        'orange-600' => '#EA580C',
    ];

    private const DEFAULT_COLORS = [
        'pattern' => 'solid',
        'primary' => 'blue-600',
        'secondary' => 'white',
        'number' => 'white',
    ];

    private static ?array $teams = null;

    private static function teams(): array
    {
        if (self::$teams === null) {
            self::$teams = array_merge(
                LaLiga::teams(),
                LaLiga2::teams(),
                PrimeraFederacionA::teams(),
                PrimeraFederacionB::teams(),
                PremierLeague::teams(),
                Bundesliga::teams(),
                Ligue1::teams(),
                SerieA::teams(),
                EuropeanTransferPool::teams(),
                WorldCup::teams(),
            );
        }

        return self::$teams;
    }

    /**
     * Get raw color config for a team (Tailwind names).
     * Used for DB storage.
     */
    public static function get(string $teamName): array
    {
        return self::teams()[$teamName] ?? self::DEFAULT_COLORS;
    }

    /**
     * Get all teams grouped by competition with hex colors (for design system).
     */
    public static function allGrouped(): array
    {
        $providers = [
            'La Liga' => LaLiga::teams(),
            'La Liga 2' => LaLiga2::teams(),
            'Primera Federación A' => PrimeraFederacionA::teams(),
            'Primera Federación B' => PrimeraFederacionB::teams(),
            'Premier League' => PremierLeague::teams(),
            'Bundesliga' => Bundesliga::teams(),
            'Ligue 1' => Ligue1::teams(),
            'Serie A' => SerieA::teams(),
            'European Transfer Pool' => EuropeanTransferPool::teams(),
            'World Cup' => WorldCup::teams(),
        ];

        $result = [];
        foreach ($providers as $label => $teams) {
            $group = [];
            foreach ($teams as $name => $colors) {
                $group[$name] = self::toHex($colors);
            }
            $result[$label] = $group;
        }

        return $result;
    }

    /**
     * Get all teams with hex colors for preview/testing.
     */
    public static function all(): array
    {
        $result = [];
        foreach (self::teams() as $name => $colors) {
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
