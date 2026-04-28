<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\ReserveTeam\Services\ReserveTeamService;
use App\Support\PositionMapper;

class ShowReserveTeam
{
    public function __construct(
        private readonly ReserveTeamService $reserveTeamService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'reserveTeam'])->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);
        abort_if($game->reserve_team_id === null, 404);

        $squad = $this->reserveTeamService->getReserveSquad($game);

        $grouped = $squad
            ->sortByDesc('overall_score')
            ->groupBy(fn ($player) => PositionMapper::getPositionGroup($player->position));

        $count = $squad->count();
        $avgAge = $count > 0 ? round($squad->avg(fn ($p) => $p->age($game->current_date)), 1) : 0;
        $avgOverall = $count > 0 ? (int) round($squad->avg('overall_score')) : 0;

        return view('squad-reserve', [
            'game' => $game,
            'reserveTeam' => $game->reserveTeam,
            'goalkeepers' => $grouped->get('Goalkeeper', collect()),
            'defenders' => $grouped->get('Defender', collect()),
            'midfielders' => $grouped->get('Midfielder', collect()),
            'forwards' => $grouped->get('Forward', collect()),
            'reserveCount' => $count,
            'avgAge' => $avgAge,
            'avgOverall' => $avgOverall,
        ]);
    }
}
