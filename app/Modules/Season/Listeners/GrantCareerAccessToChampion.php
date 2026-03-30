<?php

namespace App\Modules\Season\Listeners;

use App\Events\TournamentCompleted;
use App\Models\User;

class GrantCareerAccessToChampion
{
    public function handle(TournamentCompleted $event): void
    {
        if (! $event->isChampion) {
            return;
        }

        User::where('id', $event->userId)
            ->where('has_career_access', false)
            ->update(['has_career_access' => true]);
    }
}
