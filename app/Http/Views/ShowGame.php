<?php

namespace App\Http\Views;

class ShowGame
{
    public function __invoke(string $gameId)
    {
        dd($gameId);
    }
}
