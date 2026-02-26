<?php

namespace App\Http\Actions;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\TournamentChallenge;
use App\Modules\Squad\Services\SquadHighlightsService;

class CreateTournamentChallenge
{
    public function __construct(
        private readonly SquadHighlightsService $highlightsService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if(!$game->isTournamentMode(), 404);

        // Check tournament is complete
        $unplayed = $game->matches()->where('played', false)->count();
        if ($unplayed > 0) {
            return back()->with('error', __('season.tournament_not_complete'));
        }

        // Return existing challenge if already created
        $existing = TournamentChallenge::where('game_id', $gameId)->first();
        if ($existing) {
            return back()->with('challenge_url', $existing->getShareUrl());
        }

        // Compute result label (reuse logic from ShowTournamentEnd)
        $resultLabel = $this->computeResultLabel($game);

        // Compute team record
        $yourRecord = $this->computeTeamRecord($game);

        // Compute squad highlights
        $squadHighlights = $this->highlightsService->compute($game);

        // Get squad player transfermarkt IDs
        $squadPlayerIds = $this->highlightsService->getSquadTransfermarktIds($game);

        $challenge = TournamentChallenge::create([
            'game_id' => $gameId,
            'user_id' => $game->user_id,
            'team_id' => $game->team_id,
            'competition_id' => $game->competition_id,
            'share_token' => TournamentChallenge::generateShareToken(),
            'result_label' => $resultLabel,
            'stats' => $yourRecord,
            'squad_player_ids' => $squadPlayerIds,
            'squad_highlights' => $squadHighlights,
        ]);

        return back()->with('challenge_url', $challenge->getShareUrl());
    }

    private function computeResultLabel(Game $game): string
    {
        $knockoutTies = $game->cupTies()
            ->where('competition_id', $game->competition_id)
            ->get();

        if ($knockoutTies->isEmpty()) {
            return 'group_stage';
        }

        $maxRound = $knockoutTies->max('round_number');
        $finalTie = $knockoutTies->where('round_number', $maxRound)->first();

        if ($finalTie && $finalTie->winner_id === $game->team_id) {
            return 'champion';
        }

        if ($finalTie && ($finalTie->home_team_id === $game->team_id || $finalTie->away_team_id === $game->team_id)) {
            return 'runner_up';
        }

        $teamTies = $knockoutTies->filter(fn ($tie) =>
            $tie->home_team_id === $game->team_id || $tie->away_team_id === $game->team_id
        );

        if ($teamTies->isEmpty()) {
            return 'group_stage';
        }

        $highestRound = $teamTies->max('round_number');
        $roundsFromFinal = $maxRound - $highestRound;

        return match (true) {
            $roundsFromFinal === 0 => 'runner_up',
            $roundsFromFinal === 2 => 'semi_finalist',
            $roundsFromFinal === 3 => 'quarter_finalist',
            $roundsFromFinal === 4 => 'round_of_16',
            $roundsFromFinal === 5 => 'round_of_32',
            default => 'group_stage',
        };
    }

    private function computeTeamRecord(Game $game): array
    {
        $matches = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('played', true)
            ->where(fn ($q) => $q->where('home_team_id', $game->team_id)->orWhere('away_team_id', $game->team_id))
            ->get();

        $won = 0;
        $drawn = 0;
        $lost = 0;
        $goalsFor = 0;
        $goalsAgainst = 0;

        foreach ($matches as $match) {
            $isHome = $match->home_team_id === $game->team_id;
            $scored = $isHome ? ($match->home_score ?? 0) : ($match->away_score ?? 0);
            $conceded = $isHome ? ($match->away_score ?? 0) : ($match->home_score ?? 0);

            $goalsFor += $scored;
            $goalsAgainst += $conceded;

            if ($scored > $conceded) {
                $won++;
            } elseif ($scored === $conceded) {
                $drawn++;
            } else {
                $lost++;
            }
        }

        return [
            'played' => $matches->count(),
            'won' => $won,
            'drawn' => $drawn,
            'lost' => $lost,
            'goals_for' => $goalsFor,
            'goals_against' => $goalsAgainst,
        ];
    }
}
