<?php

namespace App\Http\Actions;

use App\Modules\Academy\Services\YouthAcademyService;
use App\Models\AcademyPlayer;
use App\Models\Game;
use Illuminate\Http\Request;

class EvaluateAcademy
{
    public function __construct(
        private readonly YouthAcademyService $youthAcademyService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $decisions = $request->input('decisions', []);

        if (empty($decisions)) {
            return redirect()->route('game.squad.academy.evaluate', $gameId)
                ->with('warning', __('messages.academy_evaluation_required'));
        }

        $tier = $game->currentInvestment->youth_academy_tier ?? 0;
        $capacity = YouthAcademyService::getCapacity($tier);

        // Load all non-loaned academy players
        $players = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', false)
            ->get()
            ->keyBy('id');

        // Validate: every player must have a decision
        foreach ($players as $player) {
            if (!isset($decisions[$player->id])) {
                return redirect()->route('game.squad.academy.evaluate', $gameId)
                    ->with('warning', __('messages.academy_evaluation_required'));
            }
        }

        // Validate: players aged 21+ cannot "keep"
        foreach ($players as $player) {
            $decision = $decisions[$player->id];
            if (YouthAcademyService::mustDecide($player) && $decision === 'keep') {
                return redirect()->route('game.squad.academy.evaluate', $gameId)
                    ->with('warning', __('messages.academy_must_decide_21'));
            }
        }

        // Calculate how many seats will be used after decisions
        $seatsAfter = 0;
        foreach ($players as $player) {
            $decision = $decisions[$player->id];
            if ($decision === 'keep') {
                $seatsAfter++;
            }
        }

        // Count loaned players (they still occupy virtual seats at season end)
        $loanedCount = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('is_on_loan', true)
            ->count();

        // Returning loans take seats at season end
        $seatsAfter += $loanedCount;

        if ($seatsAfter > $capacity && $capacity > 0) {
            $excess = $seatsAfter - $capacity;

            return redirect()->route('game.squad.academy.evaluate', $gameId)
                ->with('warning', __('messages.academy_over_capacity', ['excess' => $excess]));
        }

        // Process decisions, clearing the evaluation flag as each player is handled
        foreach ($players as $player) {
            $decision = $decisions[$player->id];

            match ($decision) {
                'keep' => $player->update(['evaluation_needed' => false]),
                'promote' => $this->youthAcademyService->promoteToFirstTeam($player, $game),
                'loan' => $this->youthAcademyService->loanPlayer($player),
                'dismiss' => $this->youthAcademyService->dismissPlayer($player),
                default => null,
            };
        }

        // Remove pending action only when all players have been evaluated
        $stillNeedsEval = AcademyPlayer::where('game_id', $gameId)
            ->where('team_id', $game->team_id)
            ->where('evaluation_needed', true)
            ->exists();

        if (!$stillNeedsEval) {
            $game->removePendingAction('academy_evaluation');
        }

        return redirect()->route('game.squad.academy', $gameId)
            ->with('success', __('messages.academy_evaluation_complete'));
    }
}
