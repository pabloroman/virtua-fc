<?php

namespace App\Http\Views;

use App\Competitions\LaLiga1;
use App\Competitions\LaLiga2;
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
