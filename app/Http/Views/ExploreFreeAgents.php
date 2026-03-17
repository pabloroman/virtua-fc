<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\ShortlistedPlayer;
use App\Modules\Transfer\Services\ScoutingService;
use App\Support\PositionMapper;
use Illuminate\Http\Request;

class ExploreFreeAgents
{
    public function __construct(
        private readonly ScoutingService $scoutingService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $query = GamePlayer::where('game_id', $gameId)
            ->whereNull('team_id')
            ->with('player');

        // Position group filter
        $positionFilter = $request->query('position', 'all');
        if ($positionFilter !== 'all') {
            $positions = match ($positionFilter) {
                'gk' => ['Goalkeeper'],
                'def' => ['Centre-Back', 'Left-Back', 'Right-Back'],
                'mid' => ['Defensive Midfield', 'Central Midfield', 'Attacking Midfield', 'Left Midfield', 'Right Midfield'],
                'fwd' => ['Left Winger', 'Right Winger', 'Centre-Forward', 'Second Striker'],
                default => [],
            };
            if (!empty($positions)) {
                $query->whereIn('position', $positions);
            }
        }

        $players = $query->get();

        // Shortlisted player IDs
        $playerIds = $players->pluck('id')->toArray();
        $shortlistedIds = ShortlistedPlayer::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->pluck('game_player_id')
            ->toArray();

        // Calculate willingness for each player and sort
        $groupOrder = ['Goalkeeper' => 0, 'Defender' => 1, 'Midfielder' => 2, 'Forward' => 3];

        $sortedPlayers = $players->map(function ($gp) use ($shortlistedIds, $game) {
            $gp->is_shortlisted = in_array($gp->id, $shortlistedIds);
            $gp->free_agent_willingness = $this->scoutingService->getFreeAgentWillingnessLevel(
                $gp, $game->id, $game->team_id
            );

            return $gp;
        })->sort(function ($a, $b) use ($groupOrder) {
            $groupA = $groupOrder[PositionMapper::getPositionGroup($a->position)] ?? 2;
            $groupB = $groupOrder[PositionMapper::getPositionGroup($b->position)] ?? 2;

            return $groupA <=> $groupB ?: $b->market_value_cents <=> $a->market_value_cents;
        });

        return view('partials.explore-free-agents', [
            'players' => $sortedPlayers,
            'game' => $game,
            'isTransferWindow' => $game->isTransferWindowOpen(),
        ]);
    }
}
