<?php

namespace App\Http\Views;

use App\Game\Services\CountryConfig;
use App\Models\Competition;
use App\Models\Game;
use App\Models\WcTeam;
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

        // Load World Cup teams grouped by confederation for tournament mode
        $wcTeams = WcTeam::orderBy('name')->get()->groupBy('confederation');
        $hasTournamentMode = $wcTeams->isNotEmpty();

        return view('select-team', [
            'countries' => $countries,
            'wcTeams' => $wcTeams,
            'hasTournamentMode' => $hasTournamentMode,
        ]);
    }
}
