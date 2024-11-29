<?php

namespace App\Http\Views;

use App\Game\Competitions\LaLiga1;
use App\Game\Competitions\LaLiga2;
use Illuminate\Http\Request;

final class SelectTeam
{
    public function __invoke(Request $request)
    {
        return view('select-team', [
            'competitions' => [
                new LaLiga1(),
                new LaLiga2(),
            ],
        ]);
    }
}
