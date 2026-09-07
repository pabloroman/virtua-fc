<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Emit the players that have no Sofascore id, as JSON for the id finder.
 *
 * Photos key off `sofascore_id`, which comes from the crosswalk built by
 * `app:build-sofascore-id-map`. Its upstream (Reep v0) is frozen and Reep v1
 * dropped Sofascore entirely, so players the crosswalk never covered can only
 * be closed out by hand in data/sofascore_ids_overrides.csv.
 *
 * This is the input side of that loop: paste the output into
 * scripts/sofascore-id-finder/, which searches Sofascore for each name and
 * confirms the hit by date of birth. Name and date of birth are what make the
 * match safe; the club is carried along for the human reviewing the leftovers.
 */
class ListUnmappedPlayers extends Command
{
    protected $signature = 'app:list-unmapped-players
                            {--season= : Season to inspect (defaults to config season.current)}
                            {--limit= : Cap the number of players emitted (handy for a trial run)}
                            {--out= : Write to this path instead of stdout}';

    protected $description = 'List players with no Sofascore id as JSON, for scripts/sofascore-id-finder/';

    public function handle(): int
    {
        $season = (string) ($this->option('season') ?: config('season.current'));

        $query = DB::table('game_player_templates as t')
            ->leftJoin('teams as c', 'c.id', '=', 't.team_id')
            ->where('t.season', $season)
            ->whereNull('t.sofascore_id')
            ->whereNotNull('t.transfermarkt_id')
            // A player can hold a template per club; one row per person is enough.
            ->distinct()
            ->orderBy('t.name')
            ->select([
                't.transfermarkt_id as tm',
                't.name',
                't.date_of_birth as dob',
                'c.name as club',
            ]);

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $rows = $query->get()
            ->map(fn ($r) => [
                'tm' => (string) $r->tm,
                'name' => (string) $r->name,
                'dob' => $r->dob === null ? null : substr((string) $r->dob, 0, 10),
                'club' => $r->club,
            ])
            ->all();

        if ($rows === []) {
            $this->info("Every {$season} player already has a Sofascore id.");

            return CommandAlias::SUCCESS;
        }

        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($path = $this->option('out')) {
            file_put_contents($path, $json."\n");
            $this->info('Wrote '.count($rows)." unmapped player(s) to {$path}");
            $this->line('Paste its contents into scripts/sofascore-id-finder/ (see its README).');

            return CommandAlias::SUCCESS;
        }

        $this->line($json);

        return CommandAlias::SUCCESS;
    }
}
