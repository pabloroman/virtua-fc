<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Player\Services\PlayerDevelopmentService;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Player\Services\PlayerValuationService;
use App\Models\Game;
use App\Models\GamePlayer;
use Carbon\Carbon;

/**
 * Applies player development changes at the end of the season.
 * Priority: 10 (runs first)
 */
class PlayerDevelopmentProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly PlayerDevelopmentService $developmentService,
        private readonly PlayerValuationService $valuationService,
        private readonly PlayerTierService $tierService,
    ) {}

    public function priority(): int
    {
        return 55;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $upsertRows = [];
        $currentDate = $game->current_date;

        // Join players table for date_of_birth (age calculation) and ability fallbacks
        GamePlayer::join('players', 'game_players.player_id', '=', 'players.id')
            ->where('game_players.game_id', $game->id)
            ->select([
                'game_players.id',
                'game_players.game_id',
                'game_players.player_id',
                'game_players.team_id',
                'game_players.position',
                'game_players.game_technical_ability',
                'game_players.game_physical_ability',
                'game_players.market_value_cents',
                'game_players.season_appearances',
                'game_players.potential',
                'players.technical_ability',
                'players.physical_ability',
                'players.date_of_birth',
            ])
            ->chunk(500, function ($chunk) use (&$upsertRows, $currentDate) {
                foreach ($chunk as $player) {
                    $age = (int) Carbon::parse($player->date_of_birth)->diffInYears($currentDate);
                    $change = $this->developmentService->calculateDevelopment($player, $age);

                    $previousAbility = (int) round(($change['techBefore'] + $change['physBefore']) / 2);
                    $newAbility = (int) round(($change['techAfter'] + $change['physAfter']) / 2);
                    $newMarketValue = $this->valuationService->abilityToMarketValue(
                        $newAbility, $age, $previousAbility
                    );

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
                }
            });

        if (!empty($upsertRows)) {
            foreach (array_chunk($upsertRows, 500) as $chunk) {
                GamePlayer::upsert($chunk, ['id'], [
                    'game_technical_ability',
                    'game_physical_ability',
                    'market_value_cents',
                ]);
            }

            $this->tierService->recomputeAllTiersForGame($game->id);
        }

        return $data;
    }
}
