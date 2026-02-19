<?php

namespace App\Modules\Season\Services;

use App\Models\Game;

class GameDeletionService
{
    public function delete(Game $game): void
    {
        // Deleting the game cascades to all FK-constrained child tables
        $game->delete();
    }
}
