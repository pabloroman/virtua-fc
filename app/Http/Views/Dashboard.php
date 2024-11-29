<?php

namespace App\Http\Views;

use App\Game\GameHeaderRepository;
use Illuminate\Http\Request;

class Dashboard
{
    public function __construct(private GameHeaderRepository $gameHeaderRepository)
    {
    }

    public function __invoke(Request $request)
    {
        $games = $this->gameHeaderRepository->getAllByUser($request->user()->id);

        if (! $games->count()) {
            return redirect()->route('select-team');
        }

        return view('dashboard', [
            'user' => $request->user(),
            'games' => $games,
        ]);
    }
}
