<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Services\PlayerDevelopmentService;
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

            if ($change['techChange'] !== 0 || $change['physChange'] !== 0) {
                // Apply development changes immediately
                $this->developmentService->applyDevelopment(
                    $player,
                    $change['techAfter'],
                    $change['physAfter']
                );

                $allChanges[] = [
                    'playerId' => $player->id,
                    'playerName' => $player->name,
                    'teamId' => $player->team_id,
                    'age' => $player->age,
                    'techBefore' => $change['techBefore'],
                    'techAfter' => $change['techAfter'],
                    'physBefore' => $change['physBefore'],
                    'physAfter' => $change['physAfter'],
                    'overallBefore' => (int) round(($change['techBefore'] + $change['physBefore']) / 2),
                    'overallAfter' => (int) round(($change['techAfter'] + $change['physAfter']) / 2),
                ];
            }
        }

        return $data->addPlayerChanges($allChanges);
    }
}
