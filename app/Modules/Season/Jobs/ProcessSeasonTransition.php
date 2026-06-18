<?php

namespace App\Modules\Season\Jobs;

use App\Events\SeasonStarted;
use App\Models\Game;
use App\Models\SeasonArchive;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Playoffs\PlayoffGeneratorFactory;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Season\Services\SeasonSetupPipeline;
use App\Support\QueryProfiler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSeasonTransition implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        public string $gameId,
    ) {
        $this->onQueue('setup');
    }

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function tags(): array
    {
        return ['game:' . $this->gameId];
    }

    public function handle(
        SeasonClosingPipeline $closingPipeline,
        SeasonSetupPipeline $setupPipeline,
        PlayoffGeneratorFactory $playoffFactory,
    ): void {
        $jobStart = microtime(true);
        $game = Game::find($this->gameId);

        if (!$game || !$game->isTransitioningSeason()) {
            return;
        }

        // Guard: a playoff may have been generated between the StartNewSeason
        // action's check and this job running (race against the last league
        // match finalising and auto-generating the semifinals). Bail cleanly
        // if any playoff is still in progress.
        foreach ($playoffFactory->all() as $generator) {
            if ($generator->state($game) === PlayoffState::InProgress) {
                Log::warning('Aborting season transition: playoff still in progress', [
                    'game_id' => $game->id,
                    'competition_id' => $generator->getCompetitionId(),
                ]);
                $game->update(['season_transitioning_at' => null]);
                return;
            }
        }

        // Restore checkpoint state for crash recovery
        $lastStep = $game->season_transition_step ?? -1;
        $restoredData = $this->restoreTransitionData($game);
        $closingProcessorCount = count($closingPipeline->getProcessors());

        // Phase 1: Close old season (skip if fully completed in a previous run)
        if ($lastStep < $closingProcessorCount - 1) {
            try {
                $data = $closingPipeline->run($game, $lastStep, $restoredData);
            } catch (PlayoffInProgressException $e) {
                // Secondary defence — the pre-flight above should have caught
                // this, but if a rule detects it mid-loop we still want to
                // bail cleanly rather than leave the flag set forever.
                Log::warning('Season closing aborted: playoff still in progress', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
                $game->update(['season_transitioning_at' => null]);
                return;
            }

            $game->refresh()->setRelations([]);
        } else {
            $data = $restoredData ?? new SeasonTransitionData(
                oldSeason: $game->season,
                newSeason: $game->season,
                competitionId: $game->competition_id,
            );
        }

        // Advance game to the new season (after closing processors finish,
        // so they all see the old season when looking up simulated data)
        $game->refresh()->setRelations([]);
        $game->update(['season' => $data->newSeason]);

        // Phase 2: Set up new season
        if (!isset($refreshedForSetup)) {
            $game->refresh()->setRelations([]);
        }
        $setupPipeline->run($game, $data, $closingProcessorCount, $lastStep);

        // Archive transition log for debugging (exclude bulky/irrelevant keys)
        $excludeKeys = [
            'loanReturns', 'preContractTransfers', 'agreedTransfers',
            'contractRenewals', 'retiredPlayers', 'retirementAnnouncements',
            'squadReplenishment', 'freeAgentSignings',
        ];
        SeasonArchive::where('game_id', $game->id)
            ->where('season', $data->oldSeason)
            ->update(['transition_log' => json_encode([
                'oldSeason' => $data->oldSeason,
                'newSeason' => $data->newSeason,
                'competitionId' => $data->competitionId,
                'metadata' => array_diff_key($data->metadata, array_flip($excludeKeys)),
            ])]);

        // Finalize: set current date and clear transition flag + checkpoint
        $game->refresh()->setRelations([]);

        // While pre-season opponents are still pending there are no fixtures
        // yet, so getFirstCompetitiveMatch() would jump current_date to the
        // first league match in August. Keep it at the July 1 pre-season start
        // (set by ContinentalAndCupInitProcessor) until the player picks their
        // friendlies — the setup screen advances the date on confirmation.
        if ($game->preseason_opponents_pending) {
            $currentDate = $game->current_date;
        } else {
            $firstMatch = $game->getFirstCompetitiveMatch();
            $fallbackDate = ((int) $game->season) . '-08-15';
            $currentDate = $firstMatch?->scheduled_date ?? $fallbackDate;
        }

        $game->update([
            'current_date' => $currentDate,
            'season_transitioning_at' => null,
            'season_transition_step' => null,
            'season_transition_data' => null,
        ]);

        event(new SeasonStarted($game));

        if (QueryProfiler::enabled()) {
            Log::info("[SeasonTransition {$game->id}] job summary", [
                'wall_ms' => (int) round((microtime(true) - $jobStart) * 1000),
                'closing_processors' => count($closingPipeline->getProcessors()),
                'setup_processors' => count($setupPipeline->getProcessors()),
                'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);
        }
    }

    /**
     * Restore SeasonTransitionData from the checkpoint JSON, if available.
     */
    private function restoreTransitionData(Game $game): ?SeasonTransitionData
    {
        $stored = $game->season_transition_data;

        if ($stored === null) {
            return null;
        }

        return new SeasonTransitionData(
            oldSeason: $stored['oldSeason'] ?? '',
            newSeason: $stored['newSeason'] ?? '',
            competitionId: $stored['competitionId'] ?? $game->competition_id,
            playerChanges: $stored['playerChanges'] ?? [],
            metadata: $stored['metadata'] ?? [],
        );
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Season transition failed', [
            'game_id' => $this->gameId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
