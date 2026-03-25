<?php

namespace App\Http\Views;

use App\Modules\Editor\Services\PlayerTemplateAdminService;
use Illuminate\Http\Request;

class AdminPlayerTemplates
{
    public function __invoke(Request $request, PlayerTemplateAdminService $service)
    {
        $seasons = $service->availableSeasons();
        $selectedSeason = $request->query('season', $seasons[0] ?? null);

        $teams = $service->teamsWithTemplates([
            'season' => $selectedSeason,
            'search' => $request->query('search'),
        ]);

        return view('editor.player-templates.index', [
            'teams' => $teams,
            'seasons' => $seasons,
            'selectedSeason' => $selectedSeason,
            'search' => $request->query('search', ''),
        ]);
    }
}
