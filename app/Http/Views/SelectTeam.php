<?php

namespace App\Http\Views;

use App\Models\Competition;
use Illuminate\Http\Request;

final class SelectTeam
{
    public function __invoke(Request $request)
    {
        return view('select-team', [
            'competitions' => [],
        ]);
    }
}
