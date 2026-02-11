<?php

namespace App\Http\Views;

use App\Game\Services\ContractService;
use App\Game\Services\YouthAcademyService;
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

        $prospects = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->get()
            ->sortBy(fn ($p) => $this->sortOrder($p));

        $grouped = $prospects->groupBy(fn ($p) => $p->position_group);

        $expiringContractsCount = $this->contractService->getPlayersEligibleForRenewal($game)->count();

        $tier = $game->currentInvestment?->youth_academy_tier ?? 0;
        $tierDescription = YouthAcademyService::getTierDescription($tier);

        return view('squad-academy', [
            'game' => $game,
            'goalkeepers' => $grouped->get('Goalkeeper', collect()),
            'defenders' => $grouped->get('Defender', collect()),
            'midfielders' => $grouped->get('Midfielder', collect()),
            'forwards' => $grouped->get('Forward', collect()),
            'academyCount' => $prospects->count(),
            'expiringContractsCount' => $expiringContractsCount,
            'tier' => $tier,
            'tierDescription' => $tierDescription,
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
        $potential = str_pad(99 - $player->potential, 2, '0', STR_PAD_LEFT);

        return "{$positionOrder}-{$potential}";
    }
}
