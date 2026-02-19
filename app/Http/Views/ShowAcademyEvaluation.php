<?php

namespace App\Http\Views;

use App\Modules\Academy\Services\YouthAcademyService;
use App\Models\AcademyPlayer;
use App\Models\Game;

class ShowAcademyEvaluation
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $tier = $game->currentInvestment->youth_academy_tier ?? 0;
        $capacity = YouthAcademyService::getCapacity($tier);
        $arrivalsRange = YouthAcademyService::getArrivalsRange($tier);

        $players = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', false)
            ->get()
            ->sortBy(fn ($p) => $this->sortOrder($p));

        $loanedCount = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', true)
            ->count();

        $revealPhase = YouthAcademyService::getRevealPhase($game);
        $isSeasonEnd = !$game->isWinterWindowOpen() && !$game->isStartOfWinterWindow();

        return view('squad-academy-evaluation', [
            'game' => $game,
            'players' => $players,
            'capacity' => $capacity,
            'occupiedSeats' => $players->count(),
            'loanedCount' => $loanedCount,
            'arrivalsRange' => $arrivalsRange,
            'revealPhase' => $revealPhase,
            'tier' => $tier,
            'isSeasonEnd' => $isSeasonEnd,
        ]);
    }

    private function sortOrder($player): string
    {
        $positionOrder = match ($player->position_group) {
            'Goalkeeper' => '1',
            'Defender' => '2',
            'Midfielder' => '3',
            'Forward' => '4',
            default => '5',
        };
        $potential = str_pad((string) (99 - $player->potential), 2, '0', STR_PAD_LEFT);

        return "{$positionOrder}-{$potential}";
    }
}
