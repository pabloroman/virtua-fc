<?php

namespace App\Http\Views;

use App\Modules\Lineup\Services\LineupService;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\MatchAttendance;
use App\Models\PlayerSuspension;

class ShowPreMatchData
{
    public function __construct(
        private readonly LineupService $lineupService,
        private readonly MatchAttendanceService $matchAttendanceService,
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
            $suspendedPlayerIds = PlayerSuspension::suspendedPlayerIdsForCompetition($gameId, $competitionId);
            $hasInjured = false;
            $hasSuspended = false;

            $lineupPlayers = GamePlayer::with('matchState')
                ->where('game_id', $gameId)
                ->whereIn('id', $lineup)
                ->get(['id']);

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

        // Resolve attendance ahead of the modal so the user sees the figure
        // alongside the venue. resolveForMatch is idempotent — the orchestrator
        // pre-match hook usually wrote the row already.
        $attendanceRow = MatchAttendance::where('game_match_id', $match->id)->first()
            ?? $this->matchAttendanceService->resolveForMatch($match, $game);

        return view('partials.pre-match-modal-content', [
            'game' => $game,
            'match' => $match,
            'issueMessage' => $issueMessage,
            'hasIssues' => $hasIssues,
            'attendance' => $attendanceRow?->attendance,
            'attendanceCapacity' => $attendanceRow?->capacity_at_match,
            'attendancePercent' => $attendanceRow?->fillRatePercent(),
        ]);
    }
}
