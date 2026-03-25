<?php

namespace App\Http\Views;

use App\Models\GamePlayerTemplate;
use App\Models\Team;
use App\Modules\Editor\Services\PlayerTemplateAdminService;
use App\Support\CountryCodeMapper;
use Illuminate\Http\Request;

class AdminPlayerTemplateSquad
{
    public function __invoke(Request $request, string $teamId, PlayerTemplateAdminService $service)
    {
        $team = Team::findOrFail($teamId);
        $seasons = $service->availableSeasons();
        $selectedSeason = $request->query('season', $seasons[0] ?? '2025');

        $grouped = $service->squadForTeam($teamId, $selectedSeason);
        $positions = PlayerTemplateAdminService::allPositions();
        $countries = CountryCodeMapper::getAllCountries();
        sort($countries);
        $teamIdsWithSquad = GamePlayerTemplate::where('season', $selectedSeason)
            ->whereNotNull('team_id')
            ->distinct()
            ->pluck('team_id');
        $teams = Team::where('type', 'club')
            ->whereIn('id', $teamIdsWithSquad)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('editor.player-templates.squad', [
            'team' => $team,
            'grouped' => $grouped,
            'seasons' => $seasons,
            'selectedSeason' => $selectedSeason,
            'positions' => $positions,
            'countries' => $countries,
            'teams' => $teams,
        ]);
    }
}
