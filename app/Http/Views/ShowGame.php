<?php

namespace App\Http\Views;

use App\Game\GameHeaderRepository;
use App\Models\Game;
use Ramsey\Uuid\Uuid;

class ShowGame
{
    public function __construct(private GameHeaderRepository $gameHeaderRepository)
    {}

    public function __invoke(string $gameId)
    {
        $gameHeader = $this->gameHeaderRepository->getById(Uuid::fromString($gameId));

        return view('game', ['game' => $gameHeader]);
    }
}
