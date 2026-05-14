<?php

namespace App\Modules\Finance\Listeners;

use App\Models\Game;
use App\Models\GameStadium;
use App\Models\GameStadiumProject;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Stadium\Enums\StadiumProjectStatus;
use App\Modules\Stadium\Enums\StadiumProjectType;
use Illuminate\Support\Facades\DB;

/**
 * Activates any stadium project whose completion_date has been reached.
 * Every project type is calendar-based — duration is fixed at commit time
 * and isn't affected by season boundaries.
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

                match ($project->type) {
                    StadiumProjectType::Supplementary  => $this->completeSupplementary($stadium, $project),
                    StadiumProjectType::StandExpansion => $this->completeStandExpansion($stadium, $project),
                    StadiumProjectType::Rebuild        => $this->completeRebuild($stadium, $project),
                    StadiumProjectType::UefaUpgrade    => $this->completeUefaUpgrade($stadium, $project),
                };

                $project->update(['status' => StadiumProjectStatus::Completed]);

                $this->notifyCompletion($event->game, $stadium->fresh(), $project);
            }
        });
    }

    private function completeSupplementary(GameStadium $stadium, GameStadiumProject $project): void
    {
        $stadium->increment('supplementary_seats', $project->target_capacity);
    }

    private function completeStandExpansion(GameStadium $stadium, GameStadiumProject $project): void
    {
        // Stand expansion adds permanent seats on top of the existing
        // (rebuilt or base) capacity. rebuilt_capacity becomes the single
        // source of truth for permanent capacity after the first change —
        // initialise it from base_capacity on first expansion so subsequent
        // expansions accumulate cleanly.
        $currentPermanent = $stadium->rebuilt_capacity ?? $stadium->base_capacity;
        $stadium->update(['rebuilt_capacity' => $currentPermanent + $project->target_capacity]);
    }

    private function completeRebuild(GameStadium $stadium, GameStadiumProject $project): void
    {
        // Fold existing supplementary stands into the rebuilt capacity so
        // the user keeps their seats and gains back the full headroom
        // for future supletorias.
        $newBase = $project->target_capacity + $stadium->supplementary_seats;
        $stadium->update([
            'rebuilt_capacity' => $newBase,
            'supplementary_seats' => 0,
        ]);
    }

    private function completeUefaUpgrade(GameStadium $stadium, GameStadiumProject $project): void
    {
        // target_capacity stores the target UEFA level for this project type.
        $stadium->update(['rebuilt_uefa_level' => (int) $project->target_capacity]);
    }

    private function notifyCompletion(Game $game, GameStadium $stadium, GameStadiumProject $project): void
    {
        $headlineValue = $project->type === StadiumProjectType::UefaUpgrade
            ? (int) $project->target_capacity
            : $stadium->effective_capacity;

        $this->notificationService->notifyStadiumProjectCompleted(
            $game,
            $project->type,
            $headlineValue,
        );
    }
}
