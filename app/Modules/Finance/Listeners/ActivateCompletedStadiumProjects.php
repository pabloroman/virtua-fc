<?php

namespace App\Modules\Finance\Listeners;

use App\Models\GameStadium;
use App\Models\GameStadiumProject;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;
use Illuminate\Support\Facades\DB;

/**
 * Activates supplementary-stand projects when the game-universe date
 * crosses their completion_date. Rebuild projects are season-based and
 * are progressed by StadiumProjectProgressionProcessor instead.
 */
class ActivateCompletedStadiumProjects
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $due = GameStadiumProject::query()
            ->where('game_id', $event->game->id)
            ->where('type', StadiumProjectType::Supplementary->value)
            ->where('status', StadiumProjectStatus::InProgress->value)
            ->whereNotNull('completion_date')
            ->where('completion_date', '<=', $event->newDate->toDateString())
            ->get();

        if ($due->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($due, $event) {
            foreach ($due as $project) {
                $stadium = GameStadium::query()
                    ->where('game_id', $project->game_id)
                    ->where('team_id', $project->team_id)
                    ->lockForUpdate()
                    ->first();

                if (! $stadium) {
                    continue;
                }

                $stadium->increment('supplementary_seats', $project->target_capacity);
                $project->update(['status' => StadiumProjectStatus::Completed]);

                $this->notificationService->notifyStadiumProjectCompleted(
                    $event->game,
                    StadiumProjectType::Supplementary,
                    $stadium->fresh()->effective_capacity,
                );
            }
        });
    }
}
