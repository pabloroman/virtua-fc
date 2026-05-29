<?php

namespace App\Console\Commands;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Competition\Services\StandingsCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild a league's game_standings rows from its finalised matches.
 *
 * Recovery tool for the failure mode where a league's season was fully played
 * (every round-robin match has a score) but one or more game_standings rows
 * were deleted by a prior incomplete season transition — leaving entries
 * orphaned. CountrySeasonSnapshotBuilder then reads fewer played>0 rows than
 * the country config expects and throws TierStandingsMissingException
 * ("N teams, expected M") at the start of promotion/relegation, stalling the
 * transition.
 *
 * Standings are a pure function of finalised matches, so the rebuild invents
 * nothing: it re-initialises a zeroed row per CompetitionEntry team, replays
 * every finalised league match through StandingsCalculator (in chronological
 * order, so form strings come out right), and recomputes positions. Orphan
 * standings (a row whose team has no entry) are dropped; missing rows (entry
 * with no row) are restored.
 *
 * Only operates on league-type tiers (handler 'league' / 'league_with_playoff')
 * that actually have finalised GameMatch rows. Leagues the user wasn't in are
 * simulated via SimulatedSeason and have no real matches — those are skipped
 * with a warning (StandingsReader already reconciles them from the sim row).
 *
 * Dry-run by default; pass --fix to write. Idempotent: re-running on a
 * already-correct league reproduces the same table.
 */
class RebuildLeagueStandings extends Command
{
    protected $signature = 'app:rebuild-league-standings {game} {--competition=* : Limit to specific competition id(s); default auto-detects mismatched league tiers} {--fix}';

    protected $description = 'Rebuild league standings from finalised matches to repair missing/orphaned standings rows.';

    public function handle(CountryConfig $countryConfig, StandingsCalculator $calculator): int
    {
        $game = Game::find($this->argument('game'));

        if (!$game) {
            $this->error("Game {$this->argument('game')} not found.");
            return self::FAILURE;
        }

        $country = $game->country ?? 'ES';

        // Build the candidate league set from the country config: tier
        // competitions with a declared team count and a league handler.
        $leagueTiers = [];
        foreach ($countryConfig->flattenedTiers($country) as $tier) {
            $handler = $tier['handler'] ?? null;
            if (!in_array($handler, ['league', 'league_with_playoff'], true)) {
                continue;
            }
            if (empty($tier['competition']) || empty($tier['teams'])) {
                continue;
            }
            $leagueTiers[$tier['competition']] = (int) $tier['teams'];
        }

        $requested = $this->option('competition');
        if (!empty($requested)) {
            $targets = array_values(array_intersect(array_keys($leagueTiers), $requested));
            $unknown = array_diff($requested, array_keys($leagueTiers));
            foreach ($unknown as $u) {
                $this->warn("Skipping '{$u}': not a league tier for country {$country}.");
            }
        } else {
            // Auto-detect: leagues whose standings row count doesn't match the
            // entry count (the symptom that stalls the snapshot builder).
            $targets = [];
            foreach ($leagueTiers as $competitionId => $expected) {
                $entries = CompetitionEntry::where('game_id', $game->id)
                    ->where('competition_id', $competitionId)->count();
                $standings = GameStanding::where('game_id', $game->id)
                    ->where('competition_id', $competitionId)->count();
                if ($entries !== $standings) {
                    $targets[] = $competitionId;
                }
            }
        }

        if (empty($targets)) {
            $this->info('No league tiers need rebuilding (entries match standings everywhere).');
            return self::SUCCESS;
        }

        $plans = [];
        foreach ($targets as $competitionId) {
            $plan = $this->planRebuild($game, $competitionId, $leagueTiers[$competitionId], $calculator);
            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        if (empty($plans)) {
            $this->warn('Nothing to rebuild (no league with finalised matches among targets).');
            return self::SUCCESS;
        }

        foreach ($plans as $plan) {
            $this->renderPlan($plan);
        }

        if (!$this->option('fix')) {
            $this->newLine();
            $this->info('Dry run — re-run with --fix to apply. (Then app:resume-season-transition.)');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($game, $plans, $calculator) {
            foreach ($plans as $plan) {
                $this->applyRebuild($game, $plan, $calculator);
            }
        });

        $this->newLine();
        $this->info('Standings rebuilt. Run app:resume-season-transition to continue the transition.');
        return self::SUCCESS;
    }

    /**
     * Compute the would-be standings table for one competition from its
     * finalised matches, without writing anything.
     *
     * @return array{competition: string, expected: int, matches: int, entryTeamIds: list<string>, rows: list<array{team_id: string, name: string, played: int, won: int, drawn: int, lost: int, gf: int, ga: int, gd: int, points: int, status: string}>}|null
     */
    private function planRebuild(Game $game, string $competitionId, int $expected, StandingsCalculator $calculator): ?array
    {
        $matches = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNull('cup_tie_id')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderBy('round_number')
            ->orderBy('scheduled_date')
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        if ($matches->isEmpty()) {
            $this->warn("{$competitionId}: no finalised league matches — likely a simulated league. Skipping (use the SimulatedSeason path instead).");
            return null;
        }

        $entryTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();
        $entrySet = array_flip($entryTeamIds);

        $existingTeamIds = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->pluck('team_id')
            ->all();
        $existingSet = array_flip($existingTeamIds);

        // Tally each entry team's record from matches. Teams appearing in
        // matches but not in entries are ignored (orphan results — dropped).
        $tally = [];
        foreach ($entryTeamIds as $teamId) {
            $tally[$teamId] = ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'gf' => 0, 'ga' => 0, 'points' => 0];
        }
        foreach ($matches as $m) {
            foreach ([
                ['id' => $m->home_team_id, 'for' => $m->home_score, 'against' => $m->away_score],
                ['id' => $m->away_team_id, 'for' => $m->away_score, 'against' => $m->home_score],
            ] as $side) {
                if (!isset($tally[$side['id']])) {
                    continue;
                }
                $t = &$tally[$side['id']];
                $t['played']++;
                $t['gf'] += $side['for'];
                $t['ga'] += $side['against'];
                if ($side['for'] > $side['against']) {
                    $t['won']++;
                    $t['points'] += 3;
                } elseif ($side['for'] === $side['against']) {
                    $t['drawn']++;
                    $t['points'] += 1;
                } else {
                    $t['lost']++;
                }
                unset($t);
            }
        }

        $names = Team::whereIn('id', $entryTeamIds)->pluck('name', 'id');

        $rows = [];
        foreach ($tally as $teamId => $t) {
            $status = isset($existingSet[$teamId]) ? '' : 'NEW (was missing)';
            $rows[] = [
                'team_id' => $teamId,
                'name' => $names[$teamId] ?? 'Unknown',
                'played' => $t['played'],
                'won' => $t['won'],
                'drawn' => $t['drawn'],
                'lost' => $t['lost'],
                'gf' => $t['gf'],
                'ga' => $t['ga'],
                'gd' => $t['gf'] - $t['ga'],
                'points' => $t['points'],
                'status' => $status,
            ];
        }

        usort($rows, function ($a, $b) {
            return [$b['points'], $b['gd'], $b['gf']] <=> [$a['points'], $a['gd'], $a['gf']];
        });

        // Note any orphan standings that will be dropped.
        $orphans = array_diff($existingTeamIds, $entryTeamIds);
        foreach ($orphans as $orphanId) {
            $rows[] = [
                'team_id' => $orphanId,
                'name' => (Team::where('id', $orphanId)->value('name') ?? 'Unknown'),
                'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0,
                'gf' => 0, 'ga' => 0, 'gd' => 0, 'points' => 0,
                'status' => 'ORPHAN (will be dropped)',
            ];
        }

        return [
            'competition' => $competitionId,
            'expected' => $expected,
            'matches' => $matches->count(),
            'entryTeamIds' => $entryTeamIds,
            'rows' => $rows,
        ];
    }

    private function renderPlan(array $plan): void
    {
        $this->newLine();
        $this->line("=== {$plan['competition']} ===");
        $this->line("expected_teams={$plan['expected']}  entry_teams=" . count($plan['entryTeamIds']) . "  finalised_matches={$plan['matches']}");

        $pos = 0;
        $tableRows = [];
        foreach ($plan['rows'] as $row) {
            $isOrphan = str_starts_with($row['status'], 'ORPHAN');
            $label = $isOrphan ? '-' : (string) (++$pos);
            $tableRows[] = [
                $label,
                $row['name'],
                $row['played'],
                $row['won'],
                $row['drawn'],
                $row['lost'],
                $row['gf'],
                $row['ga'],
                ($row['gd'] >= 0 ? '+' : '') . $row['gd'],
                $row['points'],
                $row['status'],
            ];
        }

        $this->table(
            ['Pos', 'Team', 'P', 'W', 'D', 'L', 'GF', 'GA', 'GD', 'Pts', 'Note'],
            $tableRows,
        );
    }

    private function applyRebuild(Game $game, array $plan, StandingsCalculator $calculator): void
    {
        $competitionId = $plan['competition'];

        // Wipe the competition's standings and rebuild from the entry roster +
        // finalised matches. Deletes orphan rows and restores missing ones in
        // one deterministic pass.
        GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->delete();

        $calculator->initializeStandings($game->id, $competitionId, $plan['entryTeamIds']);

        $matchResults = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNull('cup_tie_id')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->orderBy('round_number')
            ->orderBy('scheduled_date')
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score'])
            ->map(fn ($m) => [
                'homeTeamId' => $m->home_team_id,
                'awayTeamId' => $m->away_team_id,
                'homeScore' => (int) $m->home_score,
                'awayScore' => (int) $m->away_score,
            ])
            ->all();

        $calculator->bulkUpdateAfterMatches($game->id, $competitionId, $matchResults);
        $calculator->recalculatePositions($game->id, $competitionId);

        $this->info("{$competitionId}: rebuilt " . count($plan['entryTeamIds']) . " standings rows from {$plan['matches']} matches.");
    }
}
