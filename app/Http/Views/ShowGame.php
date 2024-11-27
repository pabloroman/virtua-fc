<?php

namespace App\Http\Views;

use App\Game;

class ShowGame
{
    public function __invoke(string $gameId)
    {
        dd($gameId);
    }
}
