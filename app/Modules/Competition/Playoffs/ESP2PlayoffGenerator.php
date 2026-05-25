<?php

namespace App\Modules\Competition\Playoffs;

use App\Modules\Competition\Contracts\PlayoffGenerator;
use App\Modules\Competition\DTOs\PlayoffRoundConfig;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Promotions\PromotionSlotAllocator;
use App\Modules\Competition\Services\LeagueFixtureGenerator;
use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameStanding;

/**
 * Playoff generator for a two-round promotion playoff (semifinal + final).
 *
 * Format:
 * - Teams at qualifying positions enter playoffs
 * - Semifinal: highest vs lowest, 2nd-highest vs 2nd-lowest (two legs, lower seed hosts first leg)
 * - Final: Winners play (two legs)
 * - Winner is promoted alongside the directly promoted teams
 *
 * Originally built for Spanish Segunda División, but parameterized via constructor
 * to support any league with the same playoff format.
 *
 * Bracket seeding consults PromotionSlotAllocator so the seeded teams are
 * guaranteed disjoint from the direct-promotion list. Without this, a reserve
 * team holding a top-of-table position would push a direct-promotion claimant
 * down into the bracket's range — and that team would end up both directly
 * promoted (via standings) and the playoff winner (via the bracket they were
 * mistakenly seeded into). See PromotionSlotAllocator's class doc for context.
 */
class ESP2PlayoffGenerator implements PlayoffGenerator
{
    public function __construct(
        private readonly string $competitionId,
        private readonly int $directCount = 2,
        private readonly int $playoffCount = 4,
        private readonly int $triggerMatchday = 42,
        private readonly ?PromotionSlotAllocator $slotAllocator = null,
    ) {}

    public function getCompetitionId(): string
    {
        return $this->competitionId;
    }

    /**
     * Notional qualifying positions, derived from the slot counts. Used by
     * in-season UI/notification code (LeaguePlayoffProgressResolver) to show
     * a user at, say, position 4 a "playoff" notification mid-season. The
     * actual end-of-season seeding is computed by the allocator and may
     * include later positions when reserve teams shift slots down.
     */
    public function getQualifyingPositions(): array
    {
        return $this->playoffCount > 0
            ? range($this->directCount + 1, $this->directCount + $this->playoffCount)
            : [];
    }

    public function getDirectPromotionPositions(): array
    {
        return $this->directCount > 0 ? range(1, $this->directCount) : [];
    }

    public function getTriggerMatchday(): int
    {
        return $this->triggerMatchday;
    }

    public function getTotalRounds(): int
    {
        return 2; // Semifinal + Final
    }

    public function getRoundConfig(int $round, ?string $gameSeason = null): PlayoffRoundConfig
    {
        $competition = Competition::find($this->competitionId);
        $rounds = LeagueFixtureGenerator::loadKnockoutRounds($this->competitionId, $competition->season, $gameSeason);

        foreach ($rounds as $config) {
            if ($config->round === $round) {
                return $config;
            }
        }

        throw new \RuntimeException("No knockout round config found for {$this->competitionId} round {$round}");
    }

    public function generateMatchups(Game $game, int $round): array
    {
        return match ($round) {
            1 => $this->generateSemifinalMatchups($game),
            2 => $this->generateFinalMatchup($game),
            default => throw new \InvalidArgumentException("Invalid playoff round: {$round}"),
        };
    }

    /**
     * Semifinal matchups: highest seed vs lowest, 2nd vs 3rd.
     * Lower-seeded team hosts the first leg.
     *
     * Bracket teams come from PromotionSlotAllocator, which walks standings
     * once and assigns the first $directCount eligible teams to direct
     * promotion before handing the next $playoffCount to the bracket. Reserve
     * teams whose parent club is in the top division are filtered there, so
     * this method doesn't re-apply the reserve filter.
     */
    private function generateSemifinalMatchups(Game $game): array
    {
        $allocation = $this->getAllocator()->allocate(
            $game,
            $this->competitionId,
            $this->directCount,
            $this->playoffCount,
        );

        $bracket = $allocation->playoffQualifiers;

        if (count($bracket) < $this->playoffCount) {
            throw new \RuntimeException(
                "Not enough eligible teams for playoffs in {$this->competitionId}: "
                . "need {$this->playoffCount}, found " . count($bracket)
                . ' (after reserve filtering and direct promotions). Direct promotions '
                . 'consumed ' . count($allocation->directPromotions) . ' slots.'
            );
        }

        $teamIds = array_column($bracket, 'teamId');

        // Last vs first, second-to-last vs second
        return [
            [$teamIds[3], $teamIds[0]],
            [$teamIds[2], $teamIds[1]],
        ];
    }

    /**
     * Final matchup: Winners of the two semifinals.
     *
     * Spanish rule: "La ida se juega en el campo del equipo que acabó la
     * competición en un puesto inferior" — first leg hosted by the team
     * that finished lower in the regular-season table. Equivalently, the
     * higher-finishing winner hosts the deciding second leg.
     *
     * Home/away can't be derived from bracket position alone because either
     * semifinal can be won by the lower seed (e.g. pos 6 beats pos 3),
     * which would flip which winner is the lower finisher. Look up each
     * winner's actual ESP2 position and assign home of leg 1 to the one
     * with the larger position number.
     */
    private function generateFinalMatchup(Game $game): array
    {
        $semifinalWinners = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', 1)
            ->where('completed', true)
            ->orderBy('bracket_position')
            ->pluck('winner_id')
            ->toArray();

        if (count($semifinalWinners) !== 2) {
            throw new \RuntimeException('Cannot generate final: semifinals not complete');
        }

        $positions = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->whereIn('team_id', $semifinalWinners)
            ->pluck('position', 'team_id');

        $posFirst = $positions[$semifinalWinners[0]] ?? null;
        $posSecond = $positions[$semifinalWinners[1]] ?? null;

        if ($posFirst === null || $posSecond === null) {
            throw new \RuntimeException(
                "Missing ESP2 standings for semifinal winner(s) when building playoff final."
            );
        }

        // Lower finisher (larger position number) hosts leg 1.
        if ($posFirst > $posSecond) {
            return [[$semifinalWinners[0], $semifinalWinners[1]]];
        }

        return [[$semifinalWinners[1], $semifinalWinners[0]]];
    }

    public function isComplete(Game $game): bool
    {
        $finalTie = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->where('round_number', $this->getTotalRounds())
            ->first();

        return $finalTie !== null && $finalTie->completed === true && $finalTie->winner_id !== null;
    }

    public function state(Game $game): PlayoffState
    {
        if ($this->isComplete($game)) {
            return PlayoffState::Completed;
        }

        $anyTieExists = CupTie::where('game_id', $game->id)
            ->where('competition_id', $this->competitionId)
            ->exists();

        return $anyTieExists ? PlayoffState::InProgress : PlayoffState::NotStarted;
    }

    private function getAllocator(): PromotionSlotAllocator
    {
        return $this->slotAllocator ?? app(PromotionSlotAllocator::class);
    }
}
