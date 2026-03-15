<?php

namespace App\Http\Views;

use App\Models\Team;
use App\Models\TournamentChallenge;

class ShowChallenge
{
    public function __invoke(string $shareToken)
    {
        $challenge = TournamentChallenge::with('team')
            ->where('share_token', $shareToken)
            ->firstOrFail();

        $team = $challenge->team;

        return view('challenge', [
            'challenge' => $challenge,
            'team' => $team,
        ]);
    }
}
