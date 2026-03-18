<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\ManagerTrophy;
use App\Models\SeasonArchive;
use Illuminate\Console\Command;

class BackfillManagerTrophies extends Command
{
    protected $signature = 'app:backfill-manager-trophies';

    protected $description = 'Backfill league title trophies from season archive data';

    public function handle(): int
    {
        $archives = SeasonArchive::with('game')
            ->whereNotNull('season_awards')
            ->get();

        $this->info("Processing {$archives->count()} season archives...");

        $created = 0;

        foreach ($archives as $archive) {
            $game = $archive->game;

            if (! $game || $game->game_mode !== Game::MODE_CAREER) {
                continue;
            }

            $champion = $archive->champion;

            if (! $champion || ($champion['team_id'] ?? null) !== $game->team_id) {
                continue;
            }

            $trophy = ManagerTrophy::firstOrCreate([
                'game_id' => $game->id,
                'competition_id' => $game->competition_id,
                'season' => $archive->season,
            ], [
                'user_id' => $game->user_id,
                'team_id' => $game->team_id,
                'trophy_type' => 'league',
            ]);

            if ($trophy->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->info("Backfill complete. Created {$created} league trophy records.");
        $this->warn('Note: Cup trophies from past seasons cannot be backfilled (data was deleted during archiving).');

        return self::SUCCESS;
    }
}
