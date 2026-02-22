<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Modules\Notification\Services\NotificationService;
use App\Models\Competition;
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

        match ($competition->handler_type) {
            'swiss_format' => $this->notifySwissCompletion($event, $competition, $standing),
            'league_with_playoff' => $this->notifyLeaguePlayoffCompletion($event, $competition, $standing),
            'group_stage_cup' => $this->notifyGroupStageCompletion($event, $competition, $standing),
            default => null,
        };
    }

    private function notifySwissCompletion(MatchFinalized $event, Competition $competition, GameStanding $standing): void
    {
        if ($standing->position <= 8) {
            $this->notificationService->notifyCompetitionAdvancement(
                $event->game, $competition->id, $competition->name,
                __('cup.swiss_direct_r16'),
            );
        } elseif ($standing->position <= 24) {
            $this->notificationService->notifyCompetitionAdvancement(
                $event->game, $competition->id, $competition->name,
                __('cup.swiss_knockout_playoff'),
            );
        } else {
            $this->notificationService->notifyCompetitionElimination(
                $event->game, $competition->id, $competition->name,
                __('cup.swiss_eliminated'),
            );
        }
    }

    private function notifyLeaguePlayoffCompletion(MatchFinalized $event, Competition $competition, GameStanding $standing): void
    {
        if ($standing->position <= 2) {
            $this->notificationService->notifyCompetitionAdvancement(
                $event->game, $competition->id, $competition->name,
                __('cup.direct_promotion'),
            );
        } elseif ($standing->position <= 6) {
            $this->notificationService->notifyCompetitionAdvancement(
                $event->game, $competition->id, $competition->name,
                __('cup.promotion_playoff'),
            );
        }
    }

    private function notifyGroupStageCompletion(MatchFinalized $event, Competition $competition, GameStanding $standing): void
    {
        if ($standing->position <= 2) {
            $this->notificationService->notifyCompetitionAdvancement(
                $event->game, $competition->id, $competition->name,
                __('cup.group_stage_qualified', ['group' => $standing->group_label]),
            );
        } else {
            $this->notificationService->notifyCompetitionElimination(
                $event->game, $competition->id, $competition->name,
                __('cup.group_stage_eliminated', ['group' => $standing->group_label]),
            );
        }
    }
}
