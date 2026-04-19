<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Modules\Match\Services\FastModeService;

class ExitFastMode
{
    public function __construct(
        private readonly FastModeService $fastModeService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if ($game->isFastMode()) {
            $this->fastModeService->exit($game);
        }

        return redirect()->route('show-game', $gameId)
            ->with('info', __('messages.fast_mode_disabled'));
    }
}
