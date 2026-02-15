<?php

namespace App\Http\Views;

use App\Game\Services\CountryConfig;
use App\Models\Competition;
use App\Models\Game;
use Illuminate\Http\Request;

final class SelectTeam
{
    public function __invoke(Request $request, CountryConfig $countryConfig)
    {
        if (Game::where('user_id', $request->user()->id)->count() >= 3) {
            return redirect()->route('dashboard')->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        // Build country → tier → competition structure for the template
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

        return view('select-team', [
            'countries' => $countries,
        ]);
    }
}
