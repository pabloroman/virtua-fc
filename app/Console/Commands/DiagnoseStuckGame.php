<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

        $this->line('');
        $this->line('=== Teams with entries in multiple leagues ===');
        $leagueIds = ['ESP1', 'ESP2', 'ESP3A', 'ESP3B'];
        $multi = DB::table('competition_entries')
            ->where('game_id', $gameId)
            ->whereIn('competition_id', $leagueIds)
            ->select('team_id')
            ->selectRaw('count(*) as n')
            ->selectRaw("string_agg(competition_id, ',' ORDER BY competition_id) as comps")
            ->groupBy('team_id')
            ->havingRaw('count(*) > 1')
            ->get();
        if ($multi->isEmpty()) {
            $this->line('  (none)');
        } else {
            foreach ($multi as $row) {
                $name = Team::where('id', $row->team_id)->value('name');
                $this->line("  {$row->team_id}  {$name}  [{$row->comps}]");
            }
        }

        $this->line('');
        $this->line('=== SimulatedSeason vs current CompetitionEntry mismatches ===');
        $simRows = SimulatedSeason::where('game_id', $gameId)
            ->where('season', $game->season)
            ->whereIn('competition_id', $leagueIds)
            ->get(['competition_id', 'results']);
        if ($simRows->isEmpty()) {
            $this->line('  (no SimulatedSeason rows for this season)');
        } else {
            foreach ($simRows as $sim) {
                $simTeams = is_array($sim->results) ? $sim->results : (array) $sim->results;
                $entryTeams = CompetitionEntry::where('game_id', $gameId)
                    ->where('competition_id', $sim->competition_id)
                    ->pluck('team_id')->all();

                $inSimNotEntry = array_diff($simTeams, $entryTeams);
                $inEntryNotSim = array_diff($entryTeams, $simTeams);

                $this->line("  {$sim->competition_id}: sim_size=" . count($simTeams) . " entry_size=" . count($entryTeams));
                if (!empty($inSimNotEntry)) {
                    foreach ($inSimNotEntry as $tid) {
                        $name = Team::where('id', $tid)->value('name');
                        $otherComp = CompetitionEntry::where('game_id', $gameId)
                            ->where('team_id', $tid)
                            ->whereIn('competition_id', $leagueIds)
                            ->value('competition_id');
                        $this->line("    in sim, not in entry: {$tid}  {$name}  (currently in {$otherComp})");
                    }
                }
                if (!empty($inEntryNotSim)) {
                    foreach ($inEntryNotSim as $tid) {
                        $name = Team::where('id', $tid)->value('name');
                        $this->line("    in entry, not in sim: {$tid}  {$name}");
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
