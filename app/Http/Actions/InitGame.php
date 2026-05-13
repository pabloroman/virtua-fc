<?php

namespace App\Http\Actions;

use App\Models\ActivationEvent;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Season\Services\ActivationTracker;
use App\Modules\Season\Services\GameCreationService;
use App\Modules\Season\Services\TournamentCreationService;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InitGame
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
        private readonly TournamentCreationService $tournamentCreationService,
        private readonly ActivationTracker $activationTracker,
        private readonly JobOfferService $jobOfferService,
    ) {}

    public function __invoke(Request $request)
    {
        $gameCount = Game::where('user_id', $request->user()->id)->whereNull('deleting_at')->count();
        if ($gameCount >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        $request->validate([
            'team_id' => ['required', 'uuid'],
            'game_mode' => ['sometimes', Rule::in([Game::MODE_CAREER, Game::MODE_TOURNAMENT, Game::MODE_CAREER_PRO])],
        ]);

        $gameMode = $request->get('game_mode', Game::MODE_CAREER);

        if ($gameMode === Game::MODE_CAREER && ! $request->user()->canPlayCareerMode()) {
            return back()->withErrors(['game_mode' => __('messages.career_mode_requires_invite')]);
        }

        if ($gameMode === Game::MODE_TOURNAMENT && ! $request->user()->canPlayTournamentMode()) {
            return back()->withErrors(['game_mode' => __('messages.tournament_mode_requires_access')]);
        }

        if ($gameMode === Game::MODE_CAREER_PRO) {
            if (! $request->user()->canPlayCareerMode()) {
                return back()->withErrors(['game_mode' => __('messages.career_mode_requires_invite')]);
            }

            // Server-side check that the submitted team is in the Local-tier
            // Primera RFEF pool — without this, a crafted POST could start a
            // Pro Manager career at any club, bypassing the entry-tier
            // constraint the end-of-season ladder is built on.
            if (! $this->jobOfferService->eligibleProManagerStartingTeamIds()->contains($request->get('team_id'))) {
                return back()->withErrors(['team_id' => __('messages.invalid_pro_manager_team')]);
            }

            $game = $this->gameCreationService->create(
                userId: (string) $request->user()->id,
                teamId: $request->get('team_id'),
                gameMode: Game::MODE_CAREER_PRO,
            );

            $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, Game::MODE_CAREER_PRO);

            return redirect()->route('game.welcome', $game->id);
        }

        if ($gameMode === Game::MODE_TOURNAMENT) {
            $game = $this->tournamentCreationService->create(
                userId: (string) $request->user()->id,
                teamId: $request->get('team_id'),
            );

            $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, Game::MODE_TOURNAMENT);

            return redirect()->route('show-game', $game->id);
        }

        $game = $this->gameCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $request->get('team_id'),
            gameMode: $gameMode,
        );

        $this->activationTracker->record($request->user()->id, ActivationEvent::EVENT_GAME_CREATED, $game->id, $gameMode);

        return redirect()->route('game.welcome', $game->id);
    }
}
