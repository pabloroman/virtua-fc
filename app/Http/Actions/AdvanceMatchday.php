<?php

namespace App\Http\Actions;

use App\Events\SeasonCompleted;
use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Season\Services\ActivationTracker;
use Illuminate\Support\Facades\Log;

class AdvanceMatchday
{
    public function __construct(
        private readonly MatchdayOrchestrator $orchestrator,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(string $gameId)
    {
        // Atomic check-and-set to prevent concurrent advances
        $updated = Game::where('id', $gameId)
            ->whereNull('matchday_advancing_at')
            ->whereNull('career_actions_processing_at')
            ->update(['matchday_advancing_at' => now(), 'matchday_advance_result' => null]);

        if (! $updated) {
            return redirect()->route('show-game', $gameId);
        }

        $game = Game::findOrFail($gameId);

        try {
            $result = $this->orchestrator->advance($game);

            // Dispatch SeasonCompleted event for season_complete/done results
            if (in_array($result->type, ['season_complete', 'done'])) {
                $game->refresh();
                event(new SeasonCompleted($game));
            }

            // Record activation events
            $game->refresh();
            $this->activationTracker->record($game->user_id, ActivationEvent::EVENT_FIRST_MATCH_PLAYED, $game->id, $game->game_mode);

            $alreadyRecorded = ActivationEvent::where('user_id', $game->user_id)
                ->where('game_id', $game->id)
                ->where('event', ActivationEvent::EVENT_5_MATCHES_PLAYED)
                ->exists();

            if (! $alreadyRecorded) {
                $matchesPlayed = GameMatch::where('game_id', $game->id)
                    ->where('played', true)
                    ->where(fn ($q) => $q->where('home_team_id', $game->team_id)->orWhere('away_team_id', $game->team_id))
                    ->count();

                if ($matchesPlayed >= 5) {
                    $this->activationTracker->record($game->user_id, ActivationEvent::EVENT_5_MATCHES_PLAYED, $game->id, $game->game_mode);
                }
            }

            // Clear advancing flag
            $game->update(['matchday_advancing_at' => null]);

            // Redirect based on result type
            return match ($result->type) {
                'live_match' => redirect()->route('game.live-match', [
                    'gameId' => $gameId,
                    'matchId' => $result->matchId,
                ]),
                'season_complete' => redirect()->route('game.season-end', $gameId),
                'done' => redirect()->route('show-game', $gameId),
                'blocked' => $result->pendingAction && $result->pendingAction['route']
                    ? redirect()->route($result->pendingAction['route'], $gameId)
                        ->with('warning', __('messages.action_required'))
                    : redirect()->route('show-game', $gameId)
                        ->with('warning', __('messages.action_required')),
            };
        } catch (\Throwable $e) {
            Game::where('id', $gameId)->update([
                'matchday_advancing_at' => null,
                'matchday_advance_result' => null,
            ]);

            Log::error('Matchday advance failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('show-game', $gameId)
                ->with('error', __('messages.advance_failed'));
        }
    }
}
