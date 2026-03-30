<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Competition\ProgressResolvers\CompetitionProgressResolverFactory;
use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Notification\Services\NotificationService;
use App\Models\GameMatch;
use App\Models\GameStanding;

/**
 * After a deferred match is finalized, check if its competition's league/group
 * phase just completed and send the appropriate qualification notification.
 *
 * This runs after UpdateLeagueStandings so positions reflect the finalized match.
 */
class SendCompetitionProgressNotifications
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly CompetitionProgressResolverFactory $progressResolverFactory,
    ) {}

    public function handle(MatchFinalized $event): void
    {
        $match = $event->match;

        // Only relevant for league/group-phase matches (not knockout ties)
        if ($match->cup_tie_id !== null) {
            return;
        }

        $competition = $event->competition;
        if (! $competition) {
            return;
        }

        // Check if any unplayed league/group-phase matches remain
        $hasUnplayed = GameMatch::where('game_id', $event->game->id)
            ->where('competition_id', $competition->id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists();

        if ($hasUnplayed) {
            return;
        }

        $standing = GameStanding::where('game_id', $event->game->id)
            ->where('competition_id', $competition->id)
            ->where('team_id', $event->game->team_id)
            ->first();

        if (! $standing) {
            return;
        }

        $resolver = $this->progressResolverFactory->forHandlerType($competition->handler_type);

        if (! $resolver) {
            return;
        }

        $outcome = $resolver->resolve($event->game->id, $competition->id, $standing);

        if (! $outcome) {
            return;
        }

        $message = __($outcome->translationKey, $outcome->translationParams);

        if ($outcome->isElimination()) {
            $this->notificationService->notifyCompetitionElimination(
                $event->game, $competition->id, $competition->name, $message,
            );
        } else {
            $this->notificationService->notifyCompetitionAdvancement(
                $event->game, $competition->id, $competition->name, $message,
            );
        }
    }
}
