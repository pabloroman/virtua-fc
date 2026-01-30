<?php

namespace App\Game\Contracts;

use App\Game\DTO\SeasonTransitionData;
use App\Models\Game;

interface SeasonEndProcessor
{
    /**
     * Process the season transition.
     *
     * @param Game $game The game model
     * @param SeasonTransitionData $data Data passed between processors
     * @return SeasonTransitionData Updated data for the next processor
     */
    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData;

    /**
     * Get the priority of this processor.
     * Lower numbers run first.
     */
    public function priority(): int;
}
