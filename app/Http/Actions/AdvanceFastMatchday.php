<?php

namespace App\Http\Actions;

use App\Events\SeasonCompleted;
use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Match\Services\MatchdayOrchestrator;
use App\Modules\Season\Services\ActivationTracker;
use Illuminate\Support\Facades\Log;

class AdvanceFastMatchday
{
    public function __construct(
        private readonly MatchdayOrchestrator $orchestrator,
        private readonly ActivationTracker $activationTracker,
    ) {}

    public function __invoke(string $gameId)
    {
        // Atomic check-and-set — same pattern as AdvanceMatchday, prevents
        // concurrent advances if the user double-clicks "Simulate next match".
        $updated = Game::where('id', $gameId)
            ->where('fast_mode', true)
            ->whereNull('matchday_advancing_at')
            ->whereNull('career_actions_processing_at')
            ->update(['matchday_advancing_at' => now(), 'matchday_advance_result' => null]);

        if (! $updated) {
            return redirect()->route('game.fast-mode', $gameId);
        }

        $game = Game::findOrFail($gameId);

        try {
            $result = $this->orchestrator->advance($game, fastForward: true);

            if (in_array($result->type, ['season_complete', 'done'])) {
                $game->refresh();
                event(new SeasonCompleted($game));
            }

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

            $game->update(['matchday_advancing_at' => null]);

            return match ($result->type) {
                // Fast mode never returns live_match (the orchestrator
                // finalizes the user's match inline) — included only to
                // satisfy match exhaustiveness.
                'live_match', 'done' => redirect()->route('game.fast-mode', $gameId),
                'season_complete' => redirect()->route(
                    $game->isTournamentMode() ? 'game.tournament-end' : 'game.season-end',
                    $gameId,
                ),
                // Reaches here only on the transient career-actions-in-
                // progress race; ShowFastMode bounces to the loading screen
                // on next render. No flash needed.
                'blocked' => redirect()->route('game.fast-mode', $gameId),
            };
        } catch (\Throwable $e) {
            Game::where('id', $gameId)->update([
                'matchday_advancing_at' => null,
                'matchday_advance_result' => null,
            ]);

            Log::error('Fast-mode matchday advance failed', [
                'game_id' => $gameId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('game.fast-mode', $gameId)
                ->with('error', __('messages.advance_failed'));
        }
    }
}
