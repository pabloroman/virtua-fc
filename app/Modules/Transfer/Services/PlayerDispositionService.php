<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;
use App\Support\Money;

class PlayerDispositionService
{
    /**
     * Premium a player demands over their current wage to renew.
     */
    private const RENEWAL_PREMIUM = 1.15;

    /**
     * Disposition penalty per tier gap between player tier and team reputation.
     */
    private const AMBITION_PENALTY_PER_TIER_GAP = 0.12;

    /**
     * Default renewal contract length for prime-age players.
     */
    private const DEFAULT_RENEWAL_YEARS = 3;

    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    /**
     * Calculate a player's disposition (willingness) for a negotiation.
     *
     * Combines shared factors (morale, age, round penalty) with context-specific
     * signals to produce a 0.10–0.95 score.
     *
     * @param string $context 'renewal' | 'transfer' | 'pre_contract'
     */
    public function calculateDisposition(
        GamePlayer $player,
        string $context,
        int $round = 1,
        ?Game $buyingClubGame = null,
        ?ScoutingService $scoutingService = null,
    ): float {
        $disposition = $context === 'pre_contract' ? 0.60 : 0.50;

        // ── Shared factors ──

        // Morale
        $morale = $player->morale;
        if ($context === 'renewal') {
            if ($morale >= 80) {
                $disposition += 0.15;
            } elseif ($morale >= 60) {
                $disposition += 0.08;
            } elseif ($morale < 40) {
                $disposition -= 0.10;
            }
        } else {
            if ($morale >= 70) {
                $disposition += 0.10;
            } elseif ($morale < 40) {
                $disposition -= 0.05;
            }
        }

        // Age
        $age = $player->age($player->game->current_date);
        if ($context === 'renewal') {
            if ($age >= PlayerAge::PRIME_END) {
                $disposition += 0.12;
            } elseif ($age >= PlayerAge::primePhaseAge(0.5)) {
                $disposition += 0.05;
            } elseif ($age <= PlayerAge::YOUNG_END) {
                $disposition -= 0.08;
            }
        } elseif ($context === 'pre_contract') {
            if ($age >= PlayerAge::PRIME_END) {
                $disposition += 0.12;
            } elseif ($age <= PlayerAge::YOUNG_END) {
                $disposition -= 0.05;
            }
        } else {
            if ($age >= PlayerAge::PRIME_END) {
                $disposition += 0.10;
            } elseif ($age <= PlayerAge::YOUNG_END) {
                $disposition -= 0.05;
            }
        }

        // Round penalty
        if ($round === 2) {
            $disposition -= 0.05;
        } elseif ($round >= 3) {
            $disposition -= 0.10;
        }

        // ── Context-specific factors ──

        if ($context === 'renewal') {
            // Appearances bonus
            $appearances = $player->season_appearances ?? $player->appearances ?? 0;
            if ($appearances >= 25) {
                $disposition += 0.10;
            } elseif ($appearances >= 15) {
                $disposition += 0.05;
            } elseif ($appearances < 10) {
                $disposition -= 0.10;
            }

            // Pre-contract pressure (Jan-May)
            $game = $player->game;
            $month = $game->current_date->month;
            if ($month >= 1 && $month <= 5) {
                if ($player->relationLoaded('transferOffers')) {
                    $hasPreContractOffer = $player->transferOffers->contains(function ($offer) {
                        return $offer->offer_type === TransferOffer::TYPE_PRE_CONTRACT
                            && $offer->status === TransferOffer::STATUS_PENDING;
                    });
                } else {
                    $hasPreContractOffer = $player->transferOffers()
                        ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
                        ->where('status', TransferOffer::STATUS_PENDING)
                        ->exists();
                }

                $disposition -= $hasPreContractOffer ? 0.15 : 0.08;
            }

            // Ambition: players too good for their team want to move up
            $reputationLevel = TeamReputation::resolveLevel($player->game_id, $player->team_id);
            $teamReputationIndex = ClubProfile::getReputationTierIndex($reputationLevel);
            $playerTierIndex = $player->tier - 1;

            $tierGap = $playerTierIndex - $teamReputationIndex;
            if ($tierGap > 0) {
                $disposition -= $tierGap * self::AMBITION_PENALTY_PER_TIER_GAP;
            }
        } elseif ($context === 'transfer' && $buyingClubGame) {
            // Reputation gap between buying and current team
            $buyingRep = $buyingClubGame->team?->reputation ?? 50;
            $currentRep = $player->team?->reputation ?? 50;
            if ($buyingRep > $currentRep + 10) {
                $disposition += 0.15;
            } elseif ($buyingRep >= $currentRep - 10) {
                $disposition += 0.05;
            } else {
                $disposition -= 0.10;
            }
        } elseif ($context === 'pre_contract' && $scoutingService && $buyingClubGame?->team) {
            // Reputation modifier (multiplicative for pre-contracts)
            $reputationModifier = $scoutingService->calculateReputationModifier($buyingClubGame->team, $player);
            $disposition *= $reputationModifier;
        }

        return max(0.10, min(0.95, $disposition));
    }

    /**
     * Calculate the wage demand for a negotiation context.
     *
     * @param string $context 'renewal' | 'transfer' | 'pre_contract'
     * @return array{wage: int, contractYears: int, formattedWage: string}
     */
    public function calculateWageDemand(
        GamePlayer $player,
        string $context,
        ScoutingService $scoutingService,
    ): array {
        $age = $player->age($player->game->current_date);

        if ($context === 'renewal') {
            $minimumWage = $this->contractService->getMinimumWageForTeam($player->team);
            $marketWage = $this->contractService->calculateAnnualWage(
                $player->market_value_cents,
                $minimumWage,
                $age,
            );

            $currentWageWithPremium = (int) ($player->annual_wage * self::RENEWAL_PREMIUM);
            $demandedWage = max($currentWageWithPremium, $marketWage);

            $roundingUnit = $demandedWage < 100_000_000 ? 1_000_000 : 10_000_000;
            $demandedWage = (int) (round($demandedWage / $roundingUnit) * $roundingUnit);

            if ($demandedWage <= $player->annual_wage) {
                $demandedWage = $player->annual_wage + $roundingUnit;
            }

            $contractYears = $this->calculateRenewalYears($age);

            return [
                'wage' => $demandedWage,
                'contractYears' => $contractYears,
                'formattedWage' => Money::format($demandedWage),
            ];
        }

        if ($context === 'pre_contract') {
            $wage = $scoutingService->calculatePreContractWageDemand($player);
            $contractYears = $age >= PlayerAge::PRIME_END ? 1 : ($age >= PlayerAge::primePhaseAge(0.6) ? 2 : 3);

            return [
                'wage' => $wage,
                'contractYears' => $contractYears,
                'formattedWage' => Money::format($wage),
            ];
        }

        // transfer
        $wage = $scoutingService->calculateWageDemand($player);
        $contractYears = $age >= PlayerAge::PRIME_END ? 1 : ($age >= PlayerAge::primePhaseAge(0.6) ? 2 : 3);

        return [
            'wage' => $wage,
            'contractYears' => $contractYears,
            'formattedWage' => Money::format($wage),
        ];
    }

    /**
     * Get the mood indicator for a player disposition score.
     *
     * @return array{label: string, color: string}
     */
    public function getMoodIndicator(float $disposition, string $context = 'renewal'): array
    {
        if ($context === 'transfer' || $context === 'pre_contract') {
            if ($disposition >= 0.65) {
                return ['label' => __('transfers.mood_willing_sign'), 'color' => 'green'];
            }
            if ($disposition >= 0.40) {
                return ['label' => __('transfers.mood_open_sign'), 'color' => 'amber'];
            }

            return ['label' => __('transfers.mood_reluctant_sign'), 'color' => 'red'];
        }

        // renewal
        if ($disposition >= 0.65) {
            return ['label' => __('transfers.mood_willing'), 'color' => 'green'];
        }
        if ($disposition >= 0.40) {
            return ['label' => __('transfers.mood_open'), 'color' => 'amber'];
        }

        return ['label' => __('transfers.mood_reluctant'), 'color' => 'red'];
    }

    /**
     * Calculate renewal contract years based on age.
     */
    private function calculateRenewalYears(int $age): int
    {
        return match (true) {
            $age >= PlayerAge::PRIME_END => 1,
            $age < PlayerAge::YOUNG_END => 5,
            default => self::DEFAULT_RENEWAL_YEARS,
        };
    }
}
