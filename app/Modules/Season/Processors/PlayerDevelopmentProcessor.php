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
        $upsertRows = [];

        foreach ($players as $player) {
            $change = $this->developmentService->calculateDevelopment($player);

            $previousAbility = (int) round(($change['techBefore'] + $change['physBefore']) / 2);
            $previousMarketValue = $player->market_value_cents ?? 0;

            // Recalculate market value for ALL players (even if ability didn't change,
            // age increased by 1 which affects value)
            $newAbility = (int) round(($change['techAfter'] + $change['physAfter']) / 2);
            $newMarketValue = $this->valuationService->abilityToMarketValue(
                $newAbility,
                $player->age,
                $previousAbility
            );

            // Collect row for batch upsert (includes abilities + market value in one operation)
            $upsertRows[] = [
                'id' => $player->id,
                'game_id' => $player->game_id,
                'player_id' => $player->player_id,
                'team_id' => $player->team_id,
                'position' => $player->position,
                'game_technical_ability' => $change['techAfter'],
                'game_physical_ability' => $change['physAfter'],
                'market_value_cents' => $newMarketValue,
            ];

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

        // Batch upsert all development changes + market values in one query
        if (!empty($upsertRows)) {
            foreach (array_chunk($upsertRows, 500) as $chunk) {
                GamePlayer::upsert($chunk, ['id'], [
                    'game_technical_ability',
                    'game_physical_ability',
                    'market_value_cents',
                ]);
            }
        }

        return $data->addPlayerChanges($allChanges);
    }
}
