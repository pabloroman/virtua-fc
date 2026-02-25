<?php

namespace App\Http\Views;

use App\Modules\Competition\Services\CountryConfig;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;

final class SelectTeam
{
    public function __invoke(Request $request, CountryConfig $countryConfig)
    {
        if (Game::where('user_id', $request->user()->id)->count() >= 3) {
            return redirect()->route('dashboard')->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        // Build country → tier → competition structure for career mode
        $countries = [];

        foreach ($countryConfig->playableCountryCodes() as $code) {
            $config = $countryConfig->get($code);
            $tiers = [];

            foreach ($config['tiers'] as $tier => $tierConfig) {
                $competition = Competition::with('teams')
                    ->find($tierConfig['competition']);

                if ($competition) {
                    $tiers[$tier] = $competition;
                }
            }

            if (!empty($tiers)) {
                $countries[$code] = [
                    'name' => $config['name'],
                    'flag' => $config['flag'],
                    'tiers' => $tiers,
                ];
            }
        }

        // Load World Cup teams for tournament mode
        $wcTeams = collect();
        $wcFeaturedTeams = collect();
        $hasTournamentMode = Competition::where('id', 'WC2026')->exists();

        if ($hasTournamentMode) {
            $mappingPath = base_path('data/2025/WC2026/team_mapping.json');

            if (file_exists($mappingPath)) {
                $teamMapping = json_decode(file_get_contents($mappingPath), true);

                $uuids = collect($teamMapping)
                    ->reject(fn ($entry) => $entry['is_placeholder'] ?? false)
                    ->pluck('uuid')
                    ->all();

                $allWcTeams = Team::whereIn('id', $uuids)->get()->sortBy('name')->values();

                // Featured national teams shown as larger cards
                $featuredCodes = ['ESP', 'ARG', 'BRA', 'ENG', 'FRA', 'GER', 'POR', 'NED', 'ITA'];
                $featuredUuids = collect($teamMapping)
                    ->only($featuredCodes)
                    ->pluck('uuid')
                    ->all();

                $wcFeaturedTeams = $allWcTeams->filter(fn ($t) => in_array($t->id, $featuredUuids))->values();
                $wcTeams = $allWcTeams->reject(fn ($t) => in_array($t->id, $featuredUuids))->values();
            }
        }

        return view('select-team', [
            'countries' => $countries,
            'wcTeams' => $wcTeams,
            'wcFeaturedTeams' => $wcFeaturedTeams,
            'hasTournamentMode' => $hasTournamentMode,
        ]);
    }
}
