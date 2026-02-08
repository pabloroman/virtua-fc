<?php

namespace App\Http\Views;

use App\Game\Services\ScoutingService;
use App\Models\Competition;
use App\Models\Game;

class ShowScouting
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        $report = $this->scoutingService->getActiveReport($game);

        $scoutedPlayers = collect();
        if ($report && $report->isCompleted()) {
            $scoutedPlayers = $report->players;
        }

        // Get available leagues for the search form, grouped by country
        $leagues = Competition::where('type', 'league')
            ->orderBy('country')
            ->orderBy('tier')
            ->get();

        // Group leagues by country with display names
        $countryNames = [
            'ES' => __('transfers.country_spain'),
            'GB' => __('transfers.country_england'),
            'DE' => __('transfers.country_germany'),
            'FR' => __('transfers.country_france'),
            'IT' => __('transfers.country_italy'),
            'NL' => __('transfers.country_netherlands'),
            'PT' => __('transfers.country_portugal'),
        ];
        $leaguesByCountry = $leagues->groupBy('country')->map(function ($countryLeagues, $countryCode) use ($countryNames) {
            return [
                'name' => $countryNames[$countryCode] ?? $countryCode,
                'leagues' => $countryLeagues,
            ];
        });

        $isTransferWindow = $game->isTransferWindowOpen();
        $currentWindow = $game->getCurrentWindowName();

        return view('scouting', [
            'game' => $game,
            'report' => $report,
            'scoutedPlayers' => $scoutedPlayers,
            'leaguesByCountry' => $leaguesByCountry,
            'isTransferWindow' => $isTransferWindow,
            'currentWindow' => $currentWindow,
        ]);
    }
}
