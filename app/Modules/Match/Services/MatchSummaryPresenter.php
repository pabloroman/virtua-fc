<?php

namespace App\Modules\Match\Services;

use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Modules\Match\DTOs\MatchSummaryViewModel;

/**
 * Builds the prepared view-model for the shared match-summary partial.
 */
class MatchSummaryPresenter
{
    public function present(GameMatch $match, ?string $viewerTeamId = null): MatchSummaryViewModel
    {
        // ET-inclusive score: 90-min score + ET goals (stored separately on
        // home_score_et / away_score_et).
        $homeTotal = (int) $match->home_score + (int) ($match->home_score_et ?? 0);
        $awayTotal = (int) $match->away_score + (int) ($match->away_score_et ?? 0);
        $hasPenalties = $match->home_score_penalties !== null;

        [$homeScorers, $awayScorers] = $this->buildScorerLists($match);
        [$resultLabel, $resultColor, $resultBg] = $this->resolveViewerResult(
            $match,
            $homeTotal,
            $awayTotal,
            $hasPenalties,
            $viewerTeamId,
        );

        return new MatchSummaryViewModel(
            homeTotal: $homeTotal,
            awayTotal: $awayTotal,
            hasPenalties: $hasPenalties,
            homeScorers: $homeScorers,
            awayScorers: $awayScorers,
            resultLabel: $resultLabel,
            resultColor: $resultColor,
            resultBg: $resultBg,
        );
    }

    /**
     * Group goal events by player and join their minutes ("23', 67'"), with
     * an "(og)" suffix on own-goal minutes.
     *
     * Own goals are stored under the conceding team (the side whose player
     * kicked it in) but display under the team that benefits.
     *
     * @return array{0:array<int,array{name:string,minutes:string}>,1:array<int,array{name:string,minutes:string}>}
     */
    private function buildScorerLists(GameMatch $match): array
    {
        $goalEvents = $match->events->filter(
            fn (MatchEvent $e) => in_array($e->event_type, [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_OWN_GOAL], true)
        );

        $scorerIds = $goalEvents->pluck('game_player_id')->filter()->unique()->all();
        $names = GamePlayer::whereIn('id', $scorerIds)->pluck('name', 'id');

        $beneficiaryTeamId = function (MatchEvent $event) use ($match): string {
            if ($event->event_type === MatchEvent::TYPE_OWN_GOAL) {
                return $event->team_id === $match->home_team_id
                    ? $match->away_team_id
                    : $match->home_team_id;
            }

            return $event->team_id;
        };

        $format = fn ($events) => $events
            ->groupBy(fn (MatchEvent $e) => $names[$e->game_player_id] ?? '—')
            ->map(function ($playerEvents, $name) {
                $minutes = $playerEvents
                    ->map(function (MatchEvent $e) {
                        $label = $e->minute . "'";
                        if ($e->event_type === MatchEvent::TYPE_OWN_GOAL) {
                            $label .= ' ' . __('game.og');
                        }
                        return $label;
                    })
                    ->implode(', ');

                return ['name' => $name, 'minutes' => $minutes];
            })
            ->values()
            ->all();

        return [
            $format($goalEvents->filter(fn (MatchEvent $e) => $beneficiaryTeamId($e) === $match->home_team_id)),
            $format($goalEvents->filter(fn (MatchEvent $e) => $beneficiaryTeamId($e) === $match->away_team_id)),
        ];
    }

    /**
     * W/L/D pill (label + color + background) from the viewer's perspective.
     * Returns [null, null, null] when no viewer is supplied.
     *
     * @return array{0:?string,1:?string,2:?string}
     */
    private function resolveViewerResult(
        GameMatch $match,
        int $homeTotal,
        int $awayTotal,
        bool $hasPenalties,
        ?string $viewerTeamId,
    ): array {
        if ($viewerTeamId === null) {
            return [null, null, null];
        }

        $isHome = $match->home_team_id === $viewerTeamId;
        $yourTotal = $isHome ? $homeTotal : $awayTotal;
        $oppTotal = $isHome ? $awayTotal : $homeTotal;

        if ($yourTotal !== $oppTotal) {
            $result = $yourTotal > $oppTotal ? 'W' : 'L';
        } elseif ($hasPenalties) {
            $yourPens = $isHome ? $match->home_score_penalties : $match->away_score_penalties;
            $oppPens = $isHome ? $match->away_score_penalties : $match->home_score_penalties;
            $result = $yourPens > $oppPens ? 'W' : 'L';
        } else {
            $result = 'D';
        }

        return match ($result) {
            'W' => [
                __('game.live_result_win'),
                'text-accent-green',
                'bg-accent-green/10 border-accent-green/20',
            ],
            'L' => [
                __('game.live_result_loss'),
                'text-accent-red',
                'bg-accent-red/10 border-accent-red/20',
            ],
            'D' => [
                __('game.live_result_draw'),
                'text-text-secondary',
                'bg-surface-700 border-border-default',
            ],
        };
    }
}
