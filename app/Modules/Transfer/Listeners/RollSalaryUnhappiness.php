<?php

namespace App\Modules\Transfer\Listeners;

use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Services\DispositionService;

class RollSalaryUnhappiness
{
    public function __construct(
        private readonly DispositionService $dispositionService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $this->dispositionService->rollSalaryUnhappiness($event->game);
    }
}
