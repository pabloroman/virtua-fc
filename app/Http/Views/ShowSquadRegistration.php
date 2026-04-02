<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Squad\Services\SquadRegistrationService;

class ShowSquadRegistration
{
    public function __construct(
        private readonly SquadRegistrationService $registrationService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Only for career mode
        if (!$game->isCareerMode()) {
            return redirect()->route('show-game', $gameId);
        }

        $players = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        $grouped = $players->groupBy(function ($player) {
            return match ($player->position_group) {
                'Goalkeeper' => 'goalkeepers',
                'Defender' => 'defenders',
                'Midfielder' => 'midfielders',
                'Forward' => 'forwards',
                default => 'midfielders',
            };
        });

        $goalkeepers = ($grouped->get('goalkeepers') ?? collect())->sortByDesc('overall_score')->values();
        $defenders = ($grouped->get('defenders') ?? collect())->sortByDesc('overall_score')->values();
        $midfielders = ($grouped->get('midfielders') ?? collect())->sortByDesc('overall_score')->values();
        $forwards = ($grouped->get('forwards') ?? collect())->sortByDesc('overall_score')->values();

        // Build auto-assign suggestions
        $suggestions = $this->registrationService->buildAutoAssignSuggestions($game);

        // Build player data for Alpine
        $playerData = $players->map(function (GamePlayer $p) use ($game) {
            return [
                'id' => $p->id,
                'name' => $p->player->name,
                'position' => $p->position,
                'position_group' => $p->position_group,
                'position_abbreviation' => $p->position_abbreviation,
                'overall' => $p->overall_score,
                'age' => $p->age($game->current_date),
                'number' => $p->number,
                'technical' => $p->current_technical_ability,
                'physical' => $p->current_physical_ability,
            ];
        })->keyBy('id');

        $isReRegistration = !$game->hasPendingAction('squad_registration')
            || $players->contains(fn ($p) => $p->isRegistered());

        return view('squad-registration', [
            'game' => $game,
            'goalkeepers' => $goalkeepers,
            'defenders' => $defenders,
            'midfielders' => $midfielders,
            'forwards' => $forwards,
            'allPlayers' => $players,
            'playerData' => $playerData,
            'suggestions' => $suggestions,
            'maxStandard' => GamePlayer::MAX_REGISTERED_STANDARD,
            'academyNumberStart' => GamePlayer::ACADEMY_NUMBER_START,
            'maxAcademyAge' => GamePlayer::MAX_ACADEMY_AGE,
            'minGk' => SquadRegistrationService::MIN_REGISTERED_GK,
            'minDef' => SquadRegistrationService::MIN_REGISTERED_DEF,
            'minMid' => SquadRegistrationService::MIN_REGISTERED_MID,
            'minFwd' => SquadRegistrationService::MIN_REGISTERED_FWD,
            'minTotal' => SquadRegistrationService::MIN_REGISTERED_TOTAL,
            'isReRegistration' => $isReRegistration,
        ]);
    }
}
