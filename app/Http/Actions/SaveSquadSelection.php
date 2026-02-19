<?php

namespace App\Http\Actions;

use App\Modules\Squad\Services\InjuryService;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SaveSquadSelection
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        if (!$game->isTournamentMode() || !$game->needsOnboarding()) {
            return redirect()->route('show-game', $gameId);
        }

        $request->validate([
            'player_ids' => 'required|array|size:26',
            'player_ids.*' => 'required|string',
        ]);

        $selectedTmIds = $request->input('player_ids');

        // Load and validate against JSON candidates
        $transfermarktId = $game->team->transfermarkt_id;
        $jsonPath = base_path("data/2025/WC/teams/{$transfermarktId}.json");
        $data = json_decode(file_get_contents($jsonPath), true);
        $jsonPlayers = collect($data['players'] ?? []);
        $validTmIds = $jsonPlayers->pluck('id')->toArray();

        // Verify all selected IDs are valid candidates
        $invalidIds = array_diff($selectedTmIds, $validTmIds);
        if (!empty($invalidIds)) {
            return back()->with('error', __('squad.invalid_selection'));
        }

        // Build position lookup from JSON
        $positionByTmId = $jsonPlayers->pluck('position', 'id')->toArray();

        // Load Player models for abilities
        $playerModels = Player::whereIn('transfermarkt_id', $selectedTmIds)->get()->keyBy('transfermarkt_id');

        // Create GamePlayer records for the 26 selected players
        $playerRows = [];
        foreach ($selectedTmIds as $tmId) {
            $player = $playerModels->get($tmId);
            if (!$player) {
                continue;
            }

            $currentAbility = (int) round(
                ($player->technical_ability + $player->physical_ability) / 2
            );
            $potentialData = $this->developmentService->generatePotential(
                $player->age,
                $currentAbility
            );

            $playerRows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'player_id' => $player->id,
                'team_id' => $game->team_id,
                'number' => null,
                'position' => $positionByTmId[$tmId] ?? 'Central Midfield',
                'market_value' => null,
                'market_value_cents' => 0,
                'contract_until' => null,
                'annual_wage' => 0,
                'fitness' => rand(90, 100),
                'morale' => rand(70, 85),
                'durability' => InjuryService::generateDurability(),
                'game_technical_ability' => $player->technical_ability,
                'game_physical_ability' => $player->physical_ability,
                'potential' => $potentialData['potential'],
                'potential_low' => $potentialData['low'],
                'potential_high' => $potentialData['high'],
                'season_appearances' => 0,
            ];
        }

        GamePlayer::insert($playerRows);

        $game->completeOnboarding();

        return redirect()->route('show-game', $gameId)
            ->with('success', __('squad.squad_confirmed'));
    }
}
