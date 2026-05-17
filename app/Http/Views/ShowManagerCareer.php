<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\ManagerSeasonRecord;
use App\Modules\Manager\Services\ManagerCareerHistoryService;

/**
 * Renders the pro-manager career history page: one row per managed
 * season showing team, league, goal, final position, outcome grade,
 * and how the tenure ended. Pro Manager mode only.
 */
class ShowManagerCareer
{
    public function __construct(
        private readonly ManagerCareerHistoryService $historyService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'competition'])->findOrFail($gameId);

        abort_unless($game->isProManagerMode(), 404);

        $userId = (int) auth()->id();
        $entries = $this->historyService->historyFor($game, $userId);
        $trophiesByStint = $this->historyService->trophiesByStint($game, $userId);

        $hasOnlyInProgress = $entries->count() === 1
            && ($entries->first()['in_progress'] ?? false);

        return view('manager-career', [
            'game' => $game,
            'entries' => $entries,
            'trophiesByStint' => $trophiesByStint,
            'hasOnlyInProgress' => $hasOnlyInProgress,
            'gradeBadgeClasses' => [
                'disaster' => 'bg-accent-red/10 text-accent-red border border-accent-red/30',
                'below' => 'bg-accent-red/10 text-accent-red border border-accent-red/30',
                'met' => 'bg-accent-green/10 text-accent-green border border-accent-green/30',
                'exceeded' => 'bg-accent-blue/10 text-accent-blue border border-accent-blue/30',
                'exceptional' => 'bg-accent-blue/10 text-accent-blue border border-accent-blue/30',
            ],
            'endReasonLabels' => [
                ManagerSeasonRecord::END_REASON_STILL_ACTIVE => __('manager.career_end_still_active'),
                ManagerSeasonRecord::END_REASON_LEFT_VOLUNTARILY => __('manager.career_end_left_voluntarily'),
                ManagerSeasonRecord::END_REASON_FIRED => __('manager.career_end_fired'),
            ],
            'endReasonClasses' => [
                ManagerSeasonRecord::END_REASON_STILL_ACTIVE => 'bg-surface-700 text-text-secondary border border-border-default',
                ManagerSeasonRecord::END_REASON_LEFT_VOLUNTARILY => 'bg-accent-blue/10 text-accent-blue border border-accent-blue/30',
                ManagerSeasonRecord::END_REASON_FIRED => 'bg-accent-red/10 text-accent-red border border-accent-red/30',
            ],
        ]);
    }
}
