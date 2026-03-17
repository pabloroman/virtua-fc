<?php

namespace App\Http\Views;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\Team;
use App\Modules\Transfer\Services\ExploreService;
use Illuminate\Http\Request;

class ExploreSquad
{
    public function __construct(
        private readonly ExploreService $exploreService,
    ) {}

    public function __invoke(Request $request, string $gameId, string $teamId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        // Validate team belongs to this game
        $teamInGame = CompetitionEntry::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->exists();
        abort_unless($teamInGame, 404);

        $team = Team::findOrFail($teamId);
        $players = $this->exploreService->getSquadForTeam($game, $teamId);

        return view('partials.explore-squad', [
            'team' => $team,
            'players' => $players,
            'game' => $game,
        ]);
    }
}
