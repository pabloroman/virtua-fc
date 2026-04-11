<?php

namespace App\Modules\Competition\Contracts;

use App\Models\Game;

/**
 * A promotion/relegation rule that owns its own swap logic.
 *
 * The default rule class (ConfigDrivenPromotionRule) assumes a strict
 * one-top-division ↔ one-bottom-division swap handled by
 * PromotionRelegationProcessor::swapTeams(). Some formats — e.g. Primera RFEF,
 * where promoted teams come from three feeder competitions (ESP3A, ESP3B,
 * ESP3PO) and relegated teams must be redistributed across two groups —
 * cannot express that shape.
 *
 * Rules implementing this interface are invoked via performSwap() instead
 * of the generic swapTeams() path.
 */
interface SelfSwappingPromotionRule extends PromotionRelegationRule
{
    /**
     * Perform the full swap: move promoted teams up, move relegated teams down,
     * update competition_entries / game_standings / game.competition_id, and
     * re-sort positions in all affected competitions.
     *
     * The processor still validates count($promoted) === count($relegated)
     * before calling this method.
     *
     * @param array<array{teamId: string, position: int|string, teamName: string}> $promoted
     * @param array<array{teamId: string, position: int|string, teamName: string}> $relegated
     */
    public function performSwap(Game $game, array $promoted, array $relegated): void;
}
