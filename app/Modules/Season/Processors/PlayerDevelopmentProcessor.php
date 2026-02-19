<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use App\Modules\Squad\Services\PlayerValuationService;
use App\Models\Game;
use App\Models\GamePlayer;

/**
 * Applies player development changes at the end of the season.
 * Priority: 10 (runs first)
 */
class PlayerDevelopmentProcessor implements SeasonEndProcessor
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
        private readonly PlayerValuationService $valuationService,
    ) {}

    public function priority(): int
    {
        return 10;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Process development for ALL players in the game
        $players = GamePlayer::where('game_id', $game->id)->get();

        $allChanges = [];

        foreach ($players as $player) {
            $change = $this->developmentService->calculateDevelopment($player);

            $previousAbility = (int) round(($change['techBefore'] + $change['physBefore']) / 2);
            $previousMarketValue = $player->market_value_cents ?? 0;

            if ($change['techChange'] !== 0 || $change['physChange'] !== 0) {
                // Apply development changes immediately
                $this->developmentService->applyDevelopment(
                    $player,
                    $change['techAfter'],
                    $change['physAfter']
                );
            }

            // Recalculate market value for ALL players (even if ability didn't change,
            // age increased by 1 which affects value)
            $newAbility = (int) round(($change['techAfter'] + $change['physAfter']) / 2);
            $newMarketValue = $this->valuationService->abilityToMarketValue(
                $newAbility,
                $player->age,
                $previousAbility
            );
            $player->update(['market_value_cents' => $newMarketValue]);

            if ($change['techChange'] !== 0 || $change['physChange'] !== 0 || $newMarketValue !== $previousMarketValue) {
                $allChanges[] = [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'teamId' => $player->team_id,
                    'age' => $player->age,
                    'techBefore' => $change['techBefore'],
                    'techAfter' => $change['techAfter'],
                    'physBefore' => $change['physBefore'],
                    'physAfter' => $change['physAfter'],
                    'overallBefore' => $previousAbility,
                    'overallAfter' => $newAbility,
                    'marketValueBefore' => $previousMarketValue,
                    'marketValueAfter' => $newMarketValue,
                ];
            }
        }

        return $data->addPlayerChanges($allChanges);
    }
}
