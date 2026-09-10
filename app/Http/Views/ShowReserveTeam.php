<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\TransferOffer;
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
            ->sortByDesc(fn ($p) => $p->effective_rating)
            ->groupBy(fn ($player) => PositionMapper::getPositionGroup($player->position));

        $count = $squad->count();
        $avgAge = $count > 0 ? round($squad->avg(fn ($p) => $p->age($game->current_date)), 1) : 0;
        $avgOverall = $count > 0 ? (int) round($squad->avg(fn ($p) => $p->effective_rating)) : 0;

        // Players with a committed deal cannot be moved between squads
        // (ReserveTeamService refuses), so the call-up / send-back controls
        // are withheld for them.
        $committedPlayerIds = TransferOffer::where('game_id', $game->id)
            ->committed()
            ->whereIn('game_player_id', $squad->pluck('id'))
            ->pluck('game_player_id')
            ->flip()
            ->all();

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
            'committedPlayerIds' => $committedPlayerIds,
        ]);
    }
}
