<?php

namespace App\Http\Views;

use App\Models\Competition;
use Illuminate\Http\Request;

final class SelectTeam
{
    public function __invoke(Request $request)
    {
        $competitions = Competition::with('teams')
            ->where('type', 'league')
            ->orderBy('tier')
            ->get();

        return view('select-team', [
            'competitions' => $competitions,
        ]);
    }
}
