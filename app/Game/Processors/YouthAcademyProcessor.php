<?php

namespace App\Game\Processors;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Models\AcademyPlayer;
use App\Models\Game;

/**
 * Handles academy players at season end.
 * Academy prospects now spawn mid-season during matchday advancement,
 * so this processor just records metadata about existing academy players.
 */
class YouthAcademyProcessor implements SeasonEndProcessor
{
    public function priority(): int
    {
        return 55;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $academyCount = AcademyPlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->count();

        if ($academyCount > 0) {
            $data->setMetadata('academy_players_count', $academyCount);
        }

        return $data;
    }
}
