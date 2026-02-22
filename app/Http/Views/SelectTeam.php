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

        // Load World Cup teams grouped by group letter for tournament mode
        $wcGroups = collect();
        $hasTournamentMode = Competition::where('id', 'WC2026')->exists();

        if ($hasTournamentMode) {
            $groupsPath = base_path('data/2025/WC2026/groups.json');
            $mappingPath = base_path('data/2025/WC2026/team_mapping.json');

            if (file_exists($groupsPath) && file_exists($mappingPath)) {
                $groupsData = json_decode(file_get_contents($groupsPath), true);
                $teamMapping = json_decode(file_get_contents($mappingPath), true);

                // Collect all team UUIDs from the mapping, excluding placeholders
                $uuids = collect($teamMapping)
                    ->reject(fn ($entry) => $entry['is_placeholder'] ?? false)
                    ->pluck('uuid')
                    ->all();

                $wcTeamModels = Team::whereIn('id', $uuids)->get()->keyBy('id');

                foreach ($groupsData as $groupLabel => $groupInfo) {
                    $teams = collect();
                    foreach ($groupInfo['teams'] as $fifaCode) {
                        $mapping = $teamMapping[$fifaCode] ?? null;
                        if ($mapping && !($mapping['is_placeholder'] ?? false)) {
                            $team = $wcTeamModels->get($mapping['uuid']);
                            if ($team) {
                                $teams->push($team);
                            }
                        }
                    }
                    if ($teams->isNotEmpty()) {
                        $wcGroups[$groupLabel] = $teams;
                    }
                }
            }
        }

        return view('select-team', [
            'countries' => $countries,
            'wcGroups' => $wcGroups,
            'hasTournamentMode' => $hasTournamentMode,
        ]);
    }
}
