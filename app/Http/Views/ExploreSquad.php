<?php

namespace App\Http\Views;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Support\PositionMapper;
use Illuminate\Http\Request;

class ExploreSquad
{
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

        $players = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->with(['player', 'team'])
            ->get();

        // Get active loans for these players
        $playerIds = $players->pluck('id')->toArray();
        $activeLoans = Loan::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->active()
            ->with(['parentTeam', 'loanTeam'])
            ->get()
            ->keyBy('game_player_id');

        // Shortlisted player IDs
        $shortlistedIds = ShortlistedPlayer::where('game_id', $gameId)
            ->whereIn('game_player_id', $playerIds)
            ->pluck('game_player_id')
            ->toArray();

        // Sort by position group order, then by market value descending
        $groupOrder = ['Goalkeeper' => 0, 'Defender' => 1, 'Midfielder' => 2, 'Forward' => 3];

        $sortedPlayers = $players->map(function ($gp) use ($activeLoans, $shortlistedIds, $teamId) {
            $loan = $activeLoans->get($gp->id);
            $gp->is_loaned_in = $loan && $loan->loan_team_id === $teamId;
            $gp->is_shortlisted = in_array($gp->id, $shortlistedIds);

            return $gp;
        })->sort(function ($a, $b) use ($groupOrder) {
            $groupA = $groupOrder[PositionMapper::getPositionGroup($a->position)] ?? 2;
            $groupB = $groupOrder[PositionMapper::getPositionGroup($b->position)] ?? 2;

            return $groupA <=> $groupB ?: $b->market_value_cents <=> $a->market_value_cents;
        });

        return view('partials.explore-squad', [
            'team' => $team,
            'players' => $sortedPlayers,
            'game' => $game,
        ]);
    }
}
