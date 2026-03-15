<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Player;
use App\Models\TournamentChallenge;
use App\Modules\Season\Services\TournamentCreationService;
use App\Modules\Squad\Services\InjuryService;
use App\Modules\Squad\Services\PlayerDevelopmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AcceptChallenge
{
    public function __construct(
        private readonly TournamentCreationService $tournamentCreationService,
        private readonly PlayerDevelopmentService $developmentService,
    ) {}

    public function __invoke(Request $request, string $shareToken)
    {
        $challenge = TournamentChallenge::with('team')
            ->where('share_token', $shareToken)
            ->firstOrFail();

        $request->validate([
            'mode' => 'required|in:same_squad,own_squad',
        ]);

        // Check game limit
        $gameCount = Game::where('user_id', $request->user()->id)->count();
        if ($gameCount >= 3) {
            return back()->withErrors(['limit' => __('messages.game_limit_reached')]);
        }

        // Create a new tournament game with the same team
        $game = $this->tournamentCreationService->create(
            userId: (string) $request->user()->id,
            teamId: $challenge->team_id,
        );

        $mode = $request->input('mode');

        if ($mode === 'same_squad') {
            // Wait for setup to complete, then pre-populate the squad
            // We dispatch the squad population as a closure that runs after setup
            $this->populateSquadFromChallenge($game, $challenge);

            return redirect()->route('show-game', $game->id)
                ->with('success', __('season.challenge_accepted_same_squad'));
        }

        // own_squad mode: redirect to squad selection (normal flow)
        return redirect()->route('show-game', $game->id)
            ->with('success', __('season.challenge_accepted_own_squad'));
    }

    private function populateSquadFromChallenge(Game $game, TournamentChallenge $challenge): void
    {
        $squadTmIds = $challenge->squad_player_ids;

        if (empty($squadTmIds)) {
            return;
        }

        // Load position data from JSON
        $transfermarktId = $game->team->transfermarkt_id ?? $challenge->team->transfermarkt_id;
        $jsonPath = base_path("data/2025/WC2026/teams/{$transfermarktId}.json");

        if (!file_exists($jsonPath)) {
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        $jsonPlayers = collect($data['players'] ?? []);
        $positionByTmId = $jsonPlayers->pluck('position', 'id')->toArray();

        $playerModels = Player::whereIn('transfermarkt_id', $squadTmIds)->get()->keyBy('transfermarkt_id');

        $playerRows = [];
        foreach ($squadTmIds as $tmId) {
            $player = $playerModels->get((string) $tmId);
            if (!$player) {
                continue;
            }

            $currentAbility = (int) round(
                ($player->technical_ability + $player->physical_ability) / 2
            );
            $potentialData = $this->developmentService->generatePotential(
                $player->age,
                $currentAbility
            );

            $playerRows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'player_id' => $player->id,
                'team_id' => $game->team_id,
                'number' => null,
                'position' => $positionByTmId[$tmId] ?? 'Central Midfield',
                'market_value' => null,
                'market_value_cents' => 0,
                'contract_until' => null,
                'annual_wage' => 0,
                'fitness' => rand(90, 100),
                'morale' => rand(70, 85),
                'durability' => InjuryService::generateDurability(),
                'game_technical_ability' => $player->technical_ability,
                'game_physical_ability' => $player->physical_ability,
                'potential' => $potentialData['potential'],
                'potential_low' => $potentialData['low'],
                'potential_high' => $potentialData['high'],
                'season_appearances' => 0,
            ];
        }

        if (!empty($playerRows)) {
            GamePlayer::insert($playerRows);
            $game->completeOnboarding();
        }
    }
}
