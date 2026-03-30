<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Transfer\Services\ContractService;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TransferOffer;
use Carbon\Carbon;

/**
 * Handles players whose contracts have expired.
 * Priority: 5 (runs early, before contract renewals are applied)
 *
 * Players with contract_until <= June 30 of the ending season:
 * - User's team: released (removed from squad)
 * - AI teams: most auto-renewed for 2 years, but some become free agents
 *   (team_id = null) based on age/ability criteria. Free agents may be signed
 *   by AI teams when the transfer window closes (AITransferMarketService).
 */
class ContractExpirationProcessor implements SeasonProcessor
{
    /** Max AI players per team that can become free agents per season */
    private const MAX_FREE_AGENTS_PER_TEAM = 2;

    public function priority(): int
    {
        return 4; // Before PreContractTransferProcessor (5) and ContractRenewalProcessor (6)
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Clean up any stale renewal negotiations
        app(ContractService::class)->expireStaleNegotiations($game);

        // Clean up unsigned free agents from the previous season
        GamePlayer::where('game_id', $game->id)
            ->whereNull('team_id')
            ->delete();

        // Season ends on June 30 of the season year
        // e.g., season "2024" ends June 30, 2025; season "2025" ends June 30, 2026
        $seasonYear = (int) $data->oldSeason;
        $expirationDate = Carbon::createFromDate($seasonYear + 1, 6, 30)->endOfDay();

        // Find all players in this game whose contracts have expired
        $expiredPlayers = GamePlayer::with(['player', 'team', 'game'])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->whereNotNull('contract_until')
            ->where('contract_until', '<=', $expirationDate)
            ->whereNull('pending_annual_wage') // Exclude players who renewed
            ->get();

        // Pre-calculate team averages for AI non-renewal decisions
        $aiTeamAverages = $this->calculateAITeamAverages($game);

        $releasedPlayers = [];
        $autoRenewedPlayers = [];
        $newFreeAgents = [];

        $releasedIds = [];
        $freeAgentIds = [];
        $autoRenewedIds = [];
        $newContractEnd = Carbon::createFromDate($seasonYear + 3, 6, 30);

        // Players with agreed outgoing pre-contracts will be handled by
        // PreContractTransferProcessor — do not delete them here.
        $preContractPlayerIds = TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->where('offer_type', TransferOffer::TYPE_PRE_CONTRACT)
            ->where(function ($query) {
                $query->whereNull('direction')
                    ->orWhere('direction', '!=', TransferOffer::DIRECTION_INCOMING);
            })
            ->pluck('game_player_id')
            ->all();

        // Process user's team first (always release, except pre-contract departures)
        foreach ($expiredPlayers->where('team_id', $game->team_id) as $player) {
            if (in_array($player->id, $preContractPlayerIds)) {
                continue;
            }

            $releasedPlayers[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'teamId' => $player->team_id,
                'teamName' => $player->team->name,
            ];
            $releasedIds[] = $player->id;
        }

        // Process AI teams: decide renewals vs free agents per team
        $aiExpiredByTeam = $expiredPlayers
            ->filter(fn ($p) => $p->team_id !== $game->team_id)
            ->groupBy('team_id');

        foreach ($aiExpiredByTeam as $teamId => $teamExpiredPlayers) {
            $teamAvg = $aiTeamAverages[$teamId] ?? 55;
            $freeAgentCount = 0;

            // Sort by non-renewal likelihood (most likely to leave first)
            $sorted = $teamExpiredPlayers->sortByDesc(
                fn ($p) => $this->nonRenewalScore($p, $teamAvg)
            );

            foreach ($sorted as $player) {
                $shouldRelease = $freeAgentCount < self::MAX_FREE_AGENTS_PER_TEAM
                    && $this->shouldNotRenew($player, $teamAvg);

                if ($shouldRelease) {
                    // Become a free agent (team_id = null)
                    $freeAgentCount++;
                    $newFreeAgents[] = [
                        'playerId' => $player->id,
                        'playerName' => $player->name,
                        'position' => $player->position,
                        'teamId' => $player->team_id,
                        'teamName' => $player->team->name,
                        'age' => $player->age($game->current_date),
                    ];
                    $freeAgentIds[] = $player->id;
                } else {
                    $autoRenewedPlayers[] = [
                        'playerId' => $player->id,
                        'playerName' => $player->name,
                        'teamId' => $player->team_id,
                        'teamName' => $player->team->name,
                    ];
                    $autoRenewedIds[] = $player->id;
                }
            }
        }

        // Batch operations instead of per-player queries
        if (!empty($releasedIds)) {
            GamePlayer::whereIn('id', $releasedIds)->delete();
        }
        if (!empty($freeAgentIds)) {
            GamePlayer::whereIn('id', $freeAgentIds)->update(['team_id' => null, 'number' => null]);
        }
        if (!empty($autoRenewedIds)) {
            GamePlayer::whereIn('id', $autoRenewedIds)->update(['contract_until' => $newContractEnd]);
        }

        return $data->setMetadata('expiredContracts', $releasedPlayers)
            ->setMetadata('autoRenewedContracts', $autoRenewedPlayers)
            ->setMetadata('aiContractDepartures', $newFreeAgents);
    }

    /**
     * Determine if an AI player should NOT be renewed (become free agent).
     */
    private function shouldNotRenew(GamePlayer $player, int $teamAvg): bool
    {
        $ability = $this->getPlayerAbility($player);
        $age = $player->age($player->game->current_date);

        // Age 33+ and below team average → 70% chance
        if ($age >= 33 && $ability < $teamAvg) {
            return mt_rand(1, 100) <= 70;
        }

        // Age 30-32 and significantly below average → 30% chance
        if ($age >= 30 && $ability < $teamAvg - 10) {
            return mt_rand(1, 100) <= 30;
        }

        // Baseline random chance → 5%
        return mt_rand(1, 100) <= 5;
    }

    /**
     * Score how likely a player is to not be renewed (higher = more likely).
     */
    private function nonRenewalScore(GamePlayer $player, int $teamAvg): int
    {
        $ability = $this->getPlayerAbility($player);
        $age = $player->age($player->game->current_date);
        $score = max(0, $age - 28) + max(0, $teamAvg - $ability);

        if ($age >= 33 && $ability < $teamAvg) {
            $score += 10;
        } elseif ($age >= 30 && $ability < $teamAvg - 10) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Pre-calculate average ability for all AI teams in the game.
     */
    private function calculateAITeamAverages(Game $game): array
    {
        return GamePlayer::where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $game->team_id)
            ->selectRaw('team_id, ROUND(AVG((COALESCE(game_technical_ability, 50) + COALESCE(game_physical_ability, 50)) / 2.0)) as avg_ability')
            ->groupBy('team_id')
            ->pluck('avg_ability', 'team_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    private function getPlayerAbility(GamePlayer $player): int
    {
        return (int) round((($player->game_technical_ability ?? 50) + ($player->game_physical_ability ?? 50)) / 2);
    }
}
