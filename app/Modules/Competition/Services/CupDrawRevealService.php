<?php

namespace App\Modules\Competition\Services;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use Illuminate\Support\Collection;

/**
 * Queues and builds the "sorteo" reveal — the ceremony screen shown once, right
 * after a draw the user's team is part of.
 *
 * The draw itself is never deferred: pairings are decided and written by
 * CupDrawService (domestic cups) or SwissFormatHandler (UCL/UEL/UECL) at the
 * usual moment, synchronously inside the request where the user finalizes their
 * match. This service only records that a draw is waiting to be *watched*, and
 * later rebuilds it from the CupTie rows that already exist.
 *
 * Both draw engines call {@see record()} with the same three arguments, so
 * neither has to know anything about the reveal beyond "round N was just drawn".
 */
class CupDrawRevealService
{
    public function record(Game $game, string $competitionId, int $round): void
    {
        // Fast mode hands the dashboard to ShowFastMode, which sits above the
        // ceremony gate — markers recorded there would pile up unseen and then
        // burst on exit. Tournament mode has no drawn rounds at all (the World
        // Cup bracket follows from group positions).
        if ($game->isFastMode() || $game->isTournamentMode()) {
            return;
        }

        $ties = CupTie::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $round)
            ->get(['id', 'home_team_id', 'away_team_id']);

        // A single tie was not drawn — it follows from the round before it. This
        // covers every final and the two-team supercups in one condition.
        if ($ties->count() < 2) {
            return;
        }

        // Nothing to celebrate if the user isn't in the hat: covers both being
        // eliminated and not having entered yet (a later entry_round).
        if (!$ties->contains(fn (CupTie $tie) => $tie->involvesTeam($game->team_id))) {
            return;
        }

        $pending = $game->pending_draw_reveal ?? [];

        foreach ($pending as $entry) {
            if ($entry['competition_id'] === $competitionId && $entry['round'] === $round) {
                return;
            }
        }

        $pending[] = ['competition_id' => $competitionId, 'round' => $round];

        // Written through the passed instance on purpose: the Swiss path hands us
        // the very Game object ShowGame is holding, so an in-memory write makes
        // the marker visible to the gate in the same request.
        $game->update(['pending_draw_reveal' => $pending]);
    }

    /**
     * The payload for the ceremony screen, or null when there is nothing left to
     * show. A stale head entry (competition or ties gone) is consumed rather than
     * skipped, so the gate always makes progress instead of looping.
     *
     * @return array{competition: Competition, roundName: string, twoLegged: bool, ties: Collection<int, CupTie>, playerIndex: int|null}|null
     */
    public function build(Game $game): ?array
    {
        $pending = $game->pending_draw_reveal ?? [];

        while ($pending !== []) {
            $head = $pending[0];
            $payload = $this->buildEntry($game, $head['competition_id'], $head['round']);

            if ($payload !== null) {
                return $payload;
            }

            array_shift($pending);
            $game->update(['pending_draw_reveal' => $pending ?: null]);
        }

        return null;
    }

    /**
     * Drop the draw the user just watched. A second queued draw simply becomes
     * the new head, and the gate sends them straight back in.
     */
    public function dismiss(Game $game): void
    {
        $pending = $game->pending_draw_reveal ?? [];
        array_shift($pending);

        $game->update(['pending_draw_reveal' => $pending ?: null]);
    }

    /**
     * @return array{competition: Competition, roundName: string, twoLegged: bool, ties: Collection<int, CupTie>, playerIndex: int|null}|null
     */
    private function buildEntry(Game $game, string $competitionId, int $round): ?array
    {
        $competition = Competition::find($competitionId);

        if (!$competition) {
            return null;
        }

        $roundConfig = collect(LeagueFixtureGenerator::loadKnockoutRounds(
            $competitionId,
            $game->base_season,
            $game->season,
        ))->first(fn ($config) => $config->round === $round);

        if (!$roundConfig) {
            return null;
        }

        // `game` is eager-loaded purely to defuse an N+1: <x-cup-tie-card> calls
        // CupTie::isTwoLegged() per tie, which reaches through $tie->game to find
        // the base season. The round name itself is resolved once, above.
        $ties = CupTie::with(['homeTeam', 'awayTeam', 'firstLegMatch', 'secondLegMatch', 'game'])
            ->where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->where('round_number', $round)
            // bracket_position is null for Swiss rounds past the playoff, so the
            // id keeps the order stable (and identical between page loads).
            ->orderBy('bracket_position')
            ->orderBy('id')
            ->get();

        if ($ties->count() < 2) {
            return null;
        }

        $playerIndex = $ties->search(fn (CupTie $tie) => $tie->involvesTeam($game->team_id));

        return [
            'competition' => $competition,
            'roundName' => $roundConfig->name,
            'twoLegged' => $roundConfig->twoLegged,
            'ties' => $ties,
            'playerIndex' => $playerIndex === false ? null : $playerIndex,
        ];
    }
}
