<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Player\PlayerAge;
use App\Support\PositionMapper;

class ShowSquadRegistration
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        if (! $game->isCareerMode()) {
            return redirect()->route('game.squad', $gameId);
        }

        $blocking = $game->hasPendingAction('squad_registration');
        $editable = $game->isTransferWindowOpen() || $blocking;

        $gamePlayers = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            
            ->get();

        $players = [];
        $slots = array_fill(1, 25, null);
        $academyPlayers = [];
        $unregistered = [];

        // Show real current-date age (matches every other page) and add an
        // is_u23 flag for the badge — slot eligibility is locked to the Jan 1
        // reference date, so a player who turns 24 mid-season is still filial-
        // eligible until season close.
        $referenceDate = $game->getRegistrationReferenceDate();

        foreach ($gamePlayers as $gp) {
            $dto = [
                'id' => $gp->id,
                'name' => $gp->name,
                'position' => $gp->position,
                'position_group' => $gp->position_group,
                'position_abbreviation' => PositionMapper::toAbbreviation($gp->position),
                'overall' => $gp->effective_rating,
                'age' => $gp->age($game->current_date),
                'is_u23' => $gp->age($referenceDate) <= PlayerAge::YOUNG_END,
            ];

            $players[$gp->id] = $dto;

            if ($gp->number !== null && $gp->number >= 1 && $gp->number <= 25) {
                $slots[$gp->number] = $gp->id;
            } elseif ($gp->number !== null && $gp->number >= 26 && $gp->number <= 99) {
                $academyPlayers[] = ['id' => $gp->id, 'number' => $gp->number];
            } else {
                $unregistered[] = $gp->id;
            }
        }

        return view('squad-registration', [
            'game' => $game,
            'players' => $players,
            'slots' => $slots,
            'academyPlayers' => $academyPlayers,
            'unregistered' => $unregistered,
            'blocking' => $blocking,
            'editable' => $editable,
        ]);
    }
}
