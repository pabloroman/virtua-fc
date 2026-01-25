<?php

namespace App\Http\Views;

use App\Models\Competition;
use Illuminate\Http\Request;

final class SelectTeam
{
    public function __invoke(Request $request)
    {
        $competitions = Competition::with('teams')->get();

        return view('select-team', [
            'competitions' => $competitions,
        ]);
    }
}
