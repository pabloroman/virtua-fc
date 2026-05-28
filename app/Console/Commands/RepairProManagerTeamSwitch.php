<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ContinentalAndCupInitProcessor;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\PreSeasonFixtureProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recovery command for pro-manager games stranded by the stale-offer
 * competition_id race in ApplyPendingTeamSwitchProcessor (fixed in this same
 * branch). End-of-season job offers were stamped with the destination team's
 * current league BEFORE the closing pipeline ran. If
 * PromotionRelegationProcessor then moved the destination team between leagues
 * during closing, the setup pipeline wrote that stale offer.competition_id
 * onto Game.competition_id — so the user "managed" a league their new club
 * wasn't entered in, and SyntheticLeagueResolver silently resolved the team's
 * real league as a "non-user" league at season-close. Only Copa del Rey ties
 * (where ESPCUP competition_entries survive) remained playable.
 *
 * Per-game cleanup:
 *   1. Read competition_entries to find the team's actual league for this
 *      game. Bail if it already matches Game.competition_id.
 *   2. Wipe game_matches / match_attendances / cup_ties / game_standings —
 *      the previous setup wrote them against the wrong league.
 *   3. Update Game.competition_id to the team's real league.
 *   4. Re-run the affected setup processors:
 *      LeagueFixtureProcessor → fixtures for the real league
 *      StandingsResetProcessor → standings for the real league
 *      ContinentalAndCupInitProcessor → cup draws + current_date
 *      PreSeasonFixtureProcessor → friendlies for the user's actual team
 *
 * Budget re-projection is intentionally skipped — the user may already have
 * taken financial actions (transfers, contracts) based on the wrong tier, and
 * a re-projection would either invalidate those or silently absorb the gap.
 * Operator can run BudgetProjectionProcessor manually if needed.
 *
 * Safe to re-run: every step is idempotent given a correct Game.competition_id.
 */
class RepairProManagerTeamSwitch extends Command
{
    protected $signature = 'app:repair-pro-manager-team-switch
        {gameId? : Game UUID. Omit with --all to scan every pro-manager game.}
        {--all : Repair every detectably-affected game.}
        {--dry-run : Print what would be done without writing changes.}';

    protected $description = 'Repair a pro-manager game whose competition_id does not match the team\'s actual league.';

    public function handle(
        LeagueFixtureProcessor $fixtureProcessor,
        StandingsResetProcessor $standingsProcessor,
        ContinentalAndCupInitProcessor $cupInitProcessor,
        PreSeasonFixtureProcessor $preSeasonProcessor,
    ): int {
        $gameId = $this->argument('gameId');
        $all = $this->option('all');

        if (!$gameId && !$all) {
            $this->error('Provide a gameId or pass --all.');
            return self::FAILURE;
        }

        $gameIds = $all ? $this->findAffectedGameIds() : [$gameId];

        if (empty($gameIds)) {
            $this->info('No affected games detected.');
            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($gameIds as $id) {
            if (!$this->repairOne((string) $id, $fixtureProcessor, $standingsProcessor, $cupInitProcessor, $preSeasonProcessor)) {
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return string[]
     */
    private function findAffectedGameIds(): array
    {
        $leagueIds = Competition::where('role', Competition::ROLE_LEAGUE)->pluck('id')->all();

        $games = Game::query()
            ->where('game_mode', Game::MODE_CAREER_PRO)
            ->whereNotNull('setup_completed_at')
            ->whereNull('season_transitioning_at')
            ->get(['id', 'team_id', 'competition_id']);

        $affected = [];
        foreach ($games as $game) {
            $actual = CompetitionEntry::where('game_id', $game->id)
                ->where('team_id', $game->team_id)
                ->whereIn('competition_id', $leagueIds)
                ->value('competition_id');

            if ($actual && $actual !== $game->competition_id) {
                $affected[] = $game->id;
            }
        }

        $this->info('Detected ' . count($affected) . ' affected game(s).');
        return $affected;
    }

    private function repairOne(
        string $gameId,
        LeagueFixtureProcessor $fixtureProcessor,
        StandingsResetProcessor $standingsProcessor,
        ContinentalAndCupInitProcessor $cupInitProcessor,
        PreSeasonFixtureProcessor $preSeasonProcessor,
    ): bool {
        $game = Game::find($gameId);
        if (!$game) {
            $this->error("Game {$gameId} not found.");
            return false;
        }

        if (!$game->isProManagerMode()) {
            $this->warn("Game {$gameId} is not pro-manager mode — skipping.");
            return false;
        }

        $leagueIds = Competition::where('role', Competition::ROLE_LEAGUE)->pluck('id')->all();
        $actualLeagueId = CompetitionEntry::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->whereIn('competition_id', $leagueIds)
            ->value('competition_id');

        if (!$actualLeagueId) {
            $this->error("Game {$gameId}: team {$game->team_id} is not entered in any league. Cannot recover.");
            return false;
        }

        if ($actualLeagueId === $game->competition_id) {
            $this->info("Game {$gameId}: already healthy (team in {$actualLeagueId}). Skipping.");
            return true;
        }

        $this->line(sprintf(
            'Game %s: team %s currently recorded in %s but entered in %s — will repair.',
            $gameId,
            $game->team_id,
            $game->competition_id,
            $actualLeagueId,
        ));

        if ($this->option('dry-run')) {
            return true;
        }

        try {
            DB::transaction(function () use ($game, $actualLeagueId, $fixtureProcessor, $standingsProcessor, $cupInitProcessor, $preSeasonProcessor) {
                DB::table('match_attendances')->where('game_id', $game->id)->delete();
                GameMatch::where('game_id', $game->id)->delete();
                CupTie::where('game_id', $game->id)->delete();
                GameStanding::where('game_id', $game->id)->delete();

                $game->update(['competition_id' => $actualLeagueId]);
                $game->refresh();

                $data = new SeasonTransitionData(
                    oldSeason: (string) ((int) $game->season - 1),
                    newSeason: $game->season,
                    competitionId: $actualLeagueId,
                    isInitialSeason: false,
                );

                $fixtureProcessor->process($game, $data);
                $standingsProcessor->process($game->refresh(), $data);
                $cupInitProcessor->process($game->refresh(), $data);
                $preSeasonProcessor->process($game->refresh(), $data);
            });
        } catch (\Throwable $e) {
            $this->error("Game {$gameId}: repair failed — {$e->getMessage()}");
            return false;
        }

        $this->info("Game {$gameId}: repaired to {$actualLeagueId}.");
        return true;
    }
}
