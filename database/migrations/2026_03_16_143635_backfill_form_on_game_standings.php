<?php

use App\Models\GameMatch;
use App\Models\GameStanding;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        GameStanding::whereNull('form')
            ->where('played', '>', 0)
            ->chunkById(200, function ($standings) {
                foreach ($standings as $standing) {
                    $matches = GameMatch::where('game_id', $standing->game_id)
                        ->where('competition_id', $standing->competition_id)
                        ->where('played', true)
                        ->whereNull('cup_tie_id')
                        ->where(fn ($q) => $q
                            ->where('home_team_id', $standing->team_id)
                            ->orWhere('away_team_id', $standing->team_id)
                        )
                        ->orderBy('scheduled_date')
                        ->get();

                    $form = '';
                    foreach ($matches as $match) {
                        $isHome = $match->home_team_id === $standing->team_id;
                        $teamScore = $isHome ? $match->home_score : $match->away_score;
                        $oppScore = $isHome ? $match->away_score : $match->home_score;

                        $form .= $teamScore > $oppScore ? 'W' : ($teamScore < $oppScore ? 'L' : 'D');
                    }

                    $standing->form = $form !== '' ? substr($form, -5) : null;
                    $standing->save();
                }
            });
    }

    public function down(): void
    {
        GameStanding::whereNotNull('form')->update(['form' => null]);
    }
};
