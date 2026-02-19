<?php

namespace App\Http\Views;

use App\Modules\Transfer\Services\ContractService;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Models\AcademyPlayer;
use App\Models\Game;

class ShowAcademy
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $prospects = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', false)
            ->get()
            ->sortBy(fn ($p) => $this->sortOrder($p));

        $loanedPlayers = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', true)
            ->get()
            ->sortBy(fn ($p) => $this->sortOrder($p));

        $grouped = $prospects->groupBy(fn ($p) => $p->position_group);

        $expiringContractsCount = $this->contractService->getPlayersEligibleForRenewal($game)->count();

        $tier = $game->currentInvestment->youth_academy_tier ?? 0;
        $tierDescription = YouthAcademyService::getTierDescription($tier);
        $capacity = YouthAcademyService::getCapacity($tier);
        $revealPhase = YouthAcademyService::getRevealPhase($game);

        return view('squad-academy', [
            'game' => $game,
            'goalkeepers' => $grouped->get('Goalkeeper', collect()),
            'defenders' => $grouped->get('Defender', collect()),
            'midfielders' => $grouped->get('Midfielder', collect()),
            'forwards' => $grouped->get('Forward', collect()),
            'loanedPlayers' => $loanedPlayers,
            'academyCount' => $prospects->count(),
            'expiringContractsCount' => $expiringContractsCount,
            'tier' => $tier,
            'tierDescription' => $tierDescription,
            'capacity' => $capacity,
            'revealPhase' => $revealPhase,
        ]);
    }

    private function sortOrder($player): string
    {
        // Returning players (more seasons) first, then by position, then by potential desc
        $seasons = str_pad((string) (99 - $player->seasons_in_academy), 2, '0', STR_PAD_LEFT);
        $positionOrder = match ($player->position_group) {
            'Goalkeeper' => '1',
            'Defender' => '2',
            'Midfielder' => '3',
            'Forward' => '4',
            default => '5',
        };
        $potential = str_pad((string) (99 - $player->potential), 2, '0', STR_PAD_LEFT);

        return "{$seasons}-{$positionOrder}-{$potential}";
    }
}
