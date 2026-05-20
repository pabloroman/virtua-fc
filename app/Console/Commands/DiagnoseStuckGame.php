<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use Illuminate\Console\Command;

/**
 * Print a focused state dump for a game stuck mid season-transition:
 * checkpoint step, reserve/parent coexistence map, and per-competition
 * entry/standings row counts. Read-only — no writes.
 */
class DiagnoseStuckGame extends Command
{
    protected $signature = 'app:diagnose-stuck-game {game}';

    protected $description = 'Print state of a game stuck mid season-transition (read-only).';

    public function handle(): int
    {
        $gameId = $this->argument('game');
        $game = Game::find($gameId);

        if (!$game) {
            $this->error("Game {$gameId} not found.");
            return self::FAILURE;
        }

        $this->line('=== Game ===');
        $this->line("id: {$game->id}");
        $this->line("season: {$game->season}");
        $this->line("country: {$game->country}");
        $this->line("competition_id: {$game->competition_id}");
        $this->line('season_transition_step: ' . ($game->season_transition_step ?? 'NULL'));
        $this->line('season_transitioning_at: ' . ($game->season_transitioning_at ?? 'NULL'));

        $this->line('');
        $this->line("=== Reserve teams ({$game->country}) and their parents ===");
        $reserves = Team::where('country', $game->country)
            ->whereNotNull('parent_team_id')
            ->get(['id', 'name', 'parent_team_id']);

        $tierOrder = ['ESP1' => 1, 'ESP2' => 2, 'ESP3A' => 3, 'ESP3B' => 3];

        foreach ($reserves as $r) {
            $rEntries = CompetitionEntry::where('game_id', $gameId)
                ->where('team_id', $r->id)
                ->pluck('competition_id')->all();
            $pEntries = CompetitionEntry::where('game_id', $gameId)
                ->where('team_id', $r->parent_team_id)
                ->pluck('competition_id')->all();
            $pName = Team::where('id', $r->parent_team_id)->value('name');

            $rLeague = collect($rEntries)->first(fn ($c) => isset($tierOrder[$c]));
            $pLeague = collect($pEntries)->first(fn ($c) => isset($tierOrder[$c]));

            $flags = [];
            if ($rLeague !== null && $rLeague === $pLeague) {
                $flags[] = 'COEXISTENCE';
            }
            if ($rLeague !== null && $pLeague !== null
                && ($tierOrder[$rLeague] < $tierOrder[$pLeague])
            ) {
                $flags[] = 'INVERTED';
            }

            $rEntriesStr = '[' . implode(',', $rEntries) . ']';
            $pEntriesStr = '[' . implode(',', $pEntries) . ']';
            $flagStr = empty($flags) ? '' : '  <-- ' . implode(' / ', $flags);
            $this->line("  {$r->name} {$rEntriesStr} <- parent {$pName} {$pEntriesStr}{$flagStr}");
        }

        $this->line('');
        $this->line('=== Competition entry / standings counts ===');
        foreach (['ESP1', 'ESP2', 'ESP3A', 'ESP3B'] as $c) {
            $entries = CompetitionEntry::where('game_id', $gameId)
                ->where('competition_id', $c)->count();
            $standings = GameStanding::where('game_id', $gameId)
                ->where('competition_id', $c)->count();
            $finalised = GameMatch::where('game_id', $gameId)
                ->where('competition_id', $c)
                ->whereNotNull('home_score')
                ->count();
            $this->line("  {$c}: entries={$entries}  standings={$standings}  finalised_matches={$finalised}");
        }

        $this->line('');
        $this->line('=== ESP1 entries missing from standings ===');
        $esp1Entries = CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', 'ESP1')
            ->pluck('team_id')->all();
        $esp1Standings = GameStanding::where('game_id', $gameId)
            ->where('competition_id', 'ESP1')
            ->pluck('team_id')->all();
        $missing = array_diff($esp1Entries, $esp1Standings);
        if (empty($missing)) {
            $this->line('  (none)');
        } else {
            foreach ($missing as $teamId) {
                $name = Team::where('id', $teamId)->value('name');
                $this->line("  {$teamId}  {$name}");
            }
        }

        return self::SUCCESS;
    }
}
