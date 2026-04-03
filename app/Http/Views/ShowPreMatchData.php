<?php

namespace App\Http\Views;

use App\Modules\Lineup\Services\LineupService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;

class ShowPreMatchData
{
    public function __construct(
        private readonly LineupService $lineupService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with(['team', 'tactics'])->findOrFail($gameId);
        $match = $game->next_match;

        abort_unless($match, 404);

        $match->load(['homeTeam', 'awayTeam', 'competition']);

        $matchDate = $match->scheduled_date;
        $competitionId = $match->competition_id;

        // Get the saved lineup for this match, falling back to previous match's lineup
        $lineup = $this->lineupService->getLineup($match, $game->team_id);

        $requireEnrollment = $game->requiresSquadEnrollment();

        if (empty($lineup)) {
            $previous = $this->lineupService->getPreviousLineup(
                $game->id, $game->team_id, $match->id, $matchDate, $competitionId, $requireEnrollment,
            );
            $lineup = $previous['lineup'] ?? [];
        }

        // Detect lineup issues
        $issueMessage = null;

        if (empty($lineup)) {
            $issueMessage = __('messages.pre_match_no_lineup');
        } elseif (count($lineup) < 11) {
            $issueMessage = __('messages.pre_match_incomplete');
        } else {
            $suspendedPlayerIds = PlayerSuspension::suspendedPlayerIdsForCompetition($competitionId);
            $hasInjured = false;
            $hasSuspended = false;

            $lineupPlayers = GamePlayer::where('game_id', $gameId)
                ->whereIn('id', $lineup)
                ->get(['id', 'injury_until']);

            foreach ($lineupPlayers as $player) {
                if (in_array($player->id, $suspendedPlayerIds)) {
                    $hasSuspended = true;
                } elseif ($player->injury_until && $player->injury_until->gt($matchDate)) {
                    $hasInjured = true;
                }
            }

            if ($hasInjured && $hasSuspended) {
                $issueMessage = __('messages.pre_match_unavailable_multiple');
            } elseif ($hasInjured) {
                $issueMessage = __('messages.pre_match_unavailable_injured');
            } elseif ($hasSuspended) {
                $issueMessage = __('messages.pre_match_unavailable_suspended');
            }
        }

        $hasIssues = $issueMessage !== null;

        // Skip the modal if lineup is valid (11 players, no issues)
        if (!$hasIssues && count($lineup ?? []) === 11) {
            return response()->json(['lineupReady' => true]);
        }

        return view('partials.pre-match-modal-content', [
            'game' => $game,
            'match' => $match,
            'issueMessage' => $issueMessage,
            'hasIssues' => $hasIssues,
        ]);
    }
}
