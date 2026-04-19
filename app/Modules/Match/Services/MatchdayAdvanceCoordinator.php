<?php

namespace App\Modules\Match\Services;

use App\Events\SeasonCompleted;
use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\DTOs\MatchdayAdvanceResult;
use App\Modules\Season\Services\ActivationTracker;

/**
 * Owns the cross-cutting concerns shared by the normal and fast-mode advance
 * Actions: atomic check-and-set on the advancing flag, calling the orchestrator,
 * recording activation events, dispatching SeasonCompleted, and cleaning up the
 * flag on error. Returning a MatchdayAdvanceResult keeps Actions limited to
 * mapping that result to a redirect.
 */
class MatchdayAdvanceCoordinator
{
    public function __construct(
        private readonly MatchdayOrchestrator $orchestrator,
        private readonly ActivationTracker $activationTracker,
    ) {}

    /**
     * Advance one matchday. Returns null when another request already holds
     * the advancing flag (concurrent click); the caller should treat that as
     * a no-op redirect. Re-throws orchestrator failures after clearing the
     * flag so the caller can render its own error response.
     */
    public function advance(string $gameId, bool $fastForward = false): ?MatchdayAdvanceResult
    {
        $updated = Game::where('id', $gameId)
            ->whereNull('matchday_advancing_at')
            ->whereNull('career_actions_processing_at')
            ->when($fastForward, fn ($q) => $q->whereNotNull('fast_mode_entered_on'))
            ->update(['matchday_advancing_at' => now(), 'matchday_advance_result' => null]);

        if (! $updated) {
            return null;
        }

        try {
            $game = Game::findOrFail($gameId);
            $result = $this->orchestrator->advance($game, fastForward: $fastForward);

            $game->refresh();
            $this->dispatchSeasonCompletedIfDone($game, $result);
            $this->recordActivationEvents($game);

            $game->update(['matchday_advancing_at' => null]);

            return $result;
        } catch (\Throwable $e) {
            Game::where('id', $gameId)->update([
                'matchday_advancing_at' => null,
                'matchday_advance_result' => null,
            ]);

            throw $e;
        }
    }

    /**
     * The orchestrator returns `done` whenever the user has no more matches
     * this matchday — including every fast-mode click and mid-season cup
     * eliminations. Guard the event behind an "actually no matches left"
     * check so listeners (other-leagues sim, activation analytics) only run
     * once per season.
     */
    private function dispatchSeasonCompletedIfDone(Game $game, MatchdayAdvanceResult $result): void
    {
        if ($result->type !== 'season_complete' && $result->type !== 'done') {
            return;
        }

        if ($result->type === 'done' && $game->matches()->where('played', false)->exists()) {
            return;
        }

        event(new SeasonCompleted($game));
    }

    private function recordActivationEvents(Game $game): void
    {
        $this->activationTracker->record(
            $game->user_id,
            ActivationEvent::EVENT_FIRST_MATCH_PLAYED,
            $game->id,
            $game->game_mode,
        );

        $alreadyRecorded = ActivationEvent::where('user_id', $game->user_id)
            ->where('game_id', $game->id)
            ->where('event', ActivationEvent::EVENT_5_MATCHES_PLAYED)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        $matchesPlayed = GameMatch::where('game_id', $game->id)
            ->where('played', true)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)
                ->orWhere('away_team_id', $game->team_id))
            ->count();

        if ($matchesPlayed >= 5) {
            $this->activationTracker->record(
                $game->user_id,
                ActivationEvent::EVENT_5_MATCHES_PLAYED,
                $game->id,
                $game->game_mode,
            );
        }
    }
}
