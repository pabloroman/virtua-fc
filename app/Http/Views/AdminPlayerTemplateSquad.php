<?php

namespace App\Http\Views;

use App\Models\Team;
use App\Modules\Editor\Services\PlayerTemplateAdminService;
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

        return view('admin.player-templates.squad', [
            'team' => $team,
            'grouped' => $grouped,
            'seasons' => $seasons,
            'selectedSeason' => $selectedSeason,
            'positions' => $positions,
        ]);
    }
}
