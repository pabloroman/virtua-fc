<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupNationalTeamLeaks extends Command
{
    protected $signature = 'app:cleanup-national-team-leaks
                            {--dry-run : Show what would be deleted without making changes}';

    protected $description = 'Remove WC2026 national team data that leaked into career games (post-95f26f5)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be made.');
            $this->newLine();
        }

        $nationalTeamIds = DB::table('teams')
            ->where('type', 'national')
            ->pluck('id');

        if ($nationalTeamIds->isEmpty()) {
            $this->info('No national teams found. Nothing to clean up.');
            return self::SUCCESS;
        }

        $careerGameIds = Game::where('game_mode', '!=', Game::MODE_TOURNAMENT)
            ->pluck('id');

        if ($careerGameIds->isEmpty()) {
            $this->info('No career games found. Nothing to clean up.');
            return self::SUCCESS;
        }

        // 1. Remove spurious competition entries (WC2026 in career games)
        $entryQuery = CompetitionEntry::whereIn('game_id', $careerGameIds)
            ->whereIn('team_id', $nationalTeamIds);

        $entryCount = $entryQuery->count();
        $this->line("Competition entries to remove: {$entryCount}");

        // 2. Remove spurious game players (national team players in career games)
        $playerQuery = GamePlayer::whereIn('game_id', $careerGameIds)
            ->whereIn('team_id', $nationalTeamIds);

        $playerCount = $playerQuery->count();
        $this->line("Game players to remove: {$playerCount}");

        // 3. Find stuck games (setup never completed)
        $stuckGames = Game::whereIn('id', $careerGameIds)
            ->whereNull('setup_completed_at')
            ->where('created_at', '<', now()->subMinutes(5))
            ->get(['id', 'created_at']);

        $this->line("Stuck games (setup incomplete > 5min): {$stuckGames->count()}");

        // 4. Find stuck season transitions
        $stuckTransitions = Game::whereIn('id', $careerGameIds)
            ->whereNotNull('season_transitioning_at')
            ->where('season_transitioning_at', '<', now()->subMinutes(5))
            ->get(['id', 'season_transitioning_at']);

        $this->line("Stuck season transitions (> 5min): {$stuckTransitions->count()}");

        if ($entryCount === 0 && $playerCount === 0 && $stuckGames->isEmpty() && $stuckTransitions->isEmpty()) {
            $this->newLine();
            $this->info('Nothing to clean up.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Run without --dry-run to apply changes.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Proceed with cleanup?')) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($entryQuery, $playerQuery, $stuckGames, $stuckTransitions, $entryCount, $playerCount) {
            if ($entryCount > 0) {
                $entryQuery->delete();
                $this->info("Deleted {$entryCount} competition entries.");
            }

            if ($playerCount > 0) {
                $playerQuery->delete();
                $this->info("Deleted {$playerCount} game players.");
            }

            // Re-dispatch stuck game setups so they retry with the fixed code
            foreach ($stuckGames as $game) {
                $fullGame = Game::find($game->id);
                $fullGame?->redispatchSetupJob();
                $this->info("Re-dispatched setup for game {$game->id}");
            }

            // Re-dispatch stuck season transitions
            foreach ($stuckTransitions as $game) {
                \App\Modules\Season\Jobs\ProcessSeasonTransition::dispatch($game->id);
                Game::where('id', $game->id)->update(['season_transitioning_at' => now()]);
                $this->info("Re-dispatched season transition for game {$game->id}");
            }
        });

        $this->newLine();
        $this->info('Cleanup complete.');

        return self::SUCCESS;
    }
}
