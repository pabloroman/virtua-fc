<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Models\ShortlistedPlayer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanService
{
    private const SEARCH_EXPIRY_DAYS = 21;
    private const MATCH_PROBABILITY = 50; // % chance per matchday

    /**
     * Start a loan search for a player.
     */
    public function startLoanSearch(Game $game, GamePlayer $player): void
    {
        $player->update([
            'transfer_status' => GamePlayer::TRANSFER_STATUS_LOAN_SEARCH,
            'transfer_listed_at' => $game->current_date,
        ]);
    }

    /**
     * Cancel an active loan search for a player.
     */
    public function cancelLoanSearch(GamePlayer $player): void
    {
        $player->update([
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);
    }

    /**
     * Process all active loan searches each matchday.
     * Returns arrays of found and expired results.
     */
    public function processLoanSearches(Game $game): array
    {
        $searching = GamePlayer::with(['player'])
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LOAN_SEARCH)
            ->get();

        $found = [];
        $expired = [];

        foreach ($searching as $player) {
            // Roll probability
            if (rand(1, 100) <= self::MATCH_PROBABILITY) {
                $destination = $this->findBestDestination($game, $player);

                if ($destination) {
                    // Complete the loan
                    if ($game->isTransferWindowOpen()) {
                        TransferOffer::create([
                            'game_id' => $game->id,
                            'game_player_id' => $player->id,
                            'offering_team_id' => $destination->id,
                            'selling_team_id' => $game->team_id,
                            'offer_type' => TransferOffer::TYPE_LOAN_OUT,
                            'direction' => TransferOffer::DIRECTION_OUTGOING,
                            'transfer_fee' => 0,
                            'status' => TransferOffer::STATUS_COMPLETED,
                            'expires_at' => $game->current_date->addDays(30),
                            'game_date' => $game->current_date,
                            'resolved_at' => $game->current_date,
                        ]);

                        $this->processLoanOut($game, $player, $destination);
                    } else {
                        TransferOffer::create([
                            'game_id' => $game->id,
                            'game_player_id' => $player->id,
                            'offering_team_id' => $destination->id,
                            'selling_team_id' => $game->team_id,
                            'offer_type' => TransferOffer::TYPE_LOAN_OUT,
                            'direction' => TransferOffer::DIRECTION_OUTGOING,
                            'transfer_fee' => 0,
                            'status' => TransferOffer::STATUS_AGREED,
                            'expires_at' => $game->current_date->addDays(30),
                            'game_date' => $game->current_date,
                            'resolved_at' => $game->current_date,
                        ]);

                        $player->update([
                            'transfer_status' => null,
                            'transfer_listed_at' => null,
                        ]);
                    }

                    $found[] = [
                        'player' => $player,
                        'destination' => $destination,
                        'windowOpen' => $game->isTransferWindowOpen(),
                    ];
                    continue;
                }
            }

            // Check if search has expired
            if ($this->isSearchExpired($player, $game->current_date)) {
                $player->update([
                    'transfer_status' => null,
                    'transfer_listed_at' => null,
                ]);

                $expired[] = ['player' => $player];
            }
        }

        return ['found' => $found, 'expired' => $expired];
    }

    /**
     * Find the best destination team using scoring algorithm.
     */
    private function findBestDestination(Game $game, GamePlayer $player): ?Team
    {
        $teams = Team::transferMarketEligible()
            ->with(['clubProfile', 'competitions'])
            ->whereHas('competitions', function ($q) {
                $q->where('scope', Competition::SCOPE_DOMESTIC)
                    ->where('type', 'league');
            })
            ->where('id', '!=', $game->team_id)
            ->get();

        if ($teams->isEmpty()) {
            return null;
        }

        // Pre-load position group counts for all candidate teams in one query
        $teamIds = $teams->pluck('id')->toArray();
        $positionCounts = $this->getPositionCountsByTeam($game, $teamIds);

        // Batch-load all team reputations in one query
        $teamReputations = TeamReputation::resolveLevels($game->id, $teamIds);

        // Score each team
        $scored = $teams->map(function (Team $team) use ($game, $player, $positionCounts, $teamReputations) {
            return [
                'team' => $team,
                'score' => $this->scoreLoanDestination($game, $player, $team, $positionCounts, $teamReputations),
            ];
        })
        ->filter(fn ($item) => $item['score'] >= 20)
        ->sortByDesc('score')
        ->take(5)
        ->values();

        if ($scored->isEmpty()) {
            return null;
        }

        // Weighted random from top candidates
        $totalWeight = $scored->sum('score');
        $roll = rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($scored as $item) {
            $cumulative += $item['score'];
            if ($roll <= $cumulative) {
                return $item['team'];
            }
        }

        return $scored->first()['team'];
    }

    /**
     * Score a potential loan destination (0-100).
     *
     * @param  array<string, array<string, int>>  $positionCounts  Pre-loaded position counts by team
     * @param  Collection  $teamReputations  Pre-loaded team_id => reputation_level map
     */
    private function scoreLoanDestination(Game $game, GamePlayer $player, Team $team, array $positionCounts, Collection $teamReputations): int
    {
        $score = 0;

        // Reputation match (0-40 pts)
        $score += $this->scoreReputation($player, $team, $teamReputations);

        // Position need (0-30 pts)
        $score += $this->scorePositionNeed($player, $team, $positionCounts);

        // League tier (0-20 pts)
        $score += $this->scoreLeagueTier($player, $team, $teamReputations);

        // Random variety (0-10 pts)
        $score += rand(0, 10);

        return $score;
    }

    /**
     * Score reputation match (0-40 pts).
     */
    private function scoreReputation(GamePlayer $player, Team $team, Collection $teamReputations): int
    {
        $expectedReputation = $this->getExpectedReputation($player);
        $teamReputation = $teamReputations->get($team->id, ClubProfile::REPUTATION_LOCAL);

        $expectedIndex = ClubProfile::getReputationTierIndex($expectedReputation);
        $teamIndex = ClubProfile::getReputationTierIndex($teamReputation);

        $distance = abs($expectedIndex - $teamIndex);

        return match ($distance) {
            0 => 40,
            1 => 30,
            2 => 15,
            default => 5,
        };
    }

    /**
     * Map player ability to expected reputation tier.
     */
    private function getExpectedReputation(GamePlayer $player): string
    {
        $avgAbility = (int) round(($player->current_technical_ability + $player->current_physical_ability) / 2);

        if ($avgAbility >= 82) {
            return ClubProfile::REPUTATION_ELITE;
        }
        if ($avgAbility >= 76) {
            return ClubProfile::REPUTATION_CONTINENTAL;
        }
        if ($avgAbility >= 68) {
            return ClubProfile::REPUTATION_ESTABLISHED;
        }
        if ($avgAbility >= 60) {
            return ClubProfile::REPUTATION_MODEST;
        }

        return ClubProfile::REPUTATION_LOCAL;
    }

    /**
     * Score position need (0-30 pts).
     *
     * @param  array<string, array<string, int>>  $positionCounts  Pre-loaded position counts by team
     */
    private function scorePositionNeed(GamePlayer $player, Team $team, array $positionCounts = []): int
    {
        $positionGroup = $player->position_group;
        $count = $positionCounts[$team->id][$positionGroup] ?? 0;

        return match (true) {
            $count <= 1 => 30,
            $count === 2 => 20,
            $count === 3 => 10,
            default => 0,
        };
    }

    /**
     * Pre-load position group counts for multiple teams in a single query.
     *
     * @return array<string, array<string, int>>  [teamId => [positionGroup => count]]
     */
    private function getPositionCountsByTeam(Game $game, array $teamIds): array
    {
        $players = GamePlayer::where('game_id', $game->id)
            ->whereIn('team_id', $teamIds)
            ->get(['id', 'team_id', 'position']);

        $counts = [];
        foreach ($players as $player) {
            $group = $player->position_group;
            $counts[$player->team_id][$group] = ($counts[$player->team_id][$group] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Score league tier preference (0-20 pts).
     * Uses team reputation level instead of hardcoded competition IDs.
     */
    private function scoreLeagueTier(GamePlayer $player, Team $team, Collection $teamReputations): int
    {
        $reputation = $teamReputations->get($team->id, ClubProfile::REPUTATION_LOCAL);
        $devStatus = $player->developmentStatus($player->game->current_date);
        $avgAbility = (int) round(($player->current_technical_ability + $player->current_physical_ability) / 2);

        $isSmallClub = in_array($reputation, [
            ClubProfile::REPUTATION_MODEST,
            ClubProfile::REPUTATION_LOCAL,
        ]);

        // Growing/low-ability players benefit more from smaller clubs
        if ($devStatus === 'growing' || $avgAbility < 65) {
            return $isSmallClub ? 20 : 10;
        }

        // Peak/high-ability players benefit more from bigger clubs
        if ($devStatus === 'peak' || $avgAbility >= 75) {
            return $isSmallClub ? 5 : 20;
        }

        // Middle ground
        return $isSmallClub ? 12 : 15;
    }

    /**
     * Check if a loan search has expired.
     */
    private function isSearchExpired(GamePlayer $player, Carbon $currentDate): bool
    {
        if (!$player->transfer_listed_at) {
            return true;
        }

        return $player->transfer_listed_at->diffInDays($currentDate) >= self::SEARCH_EXPIRY_DAYS;
    }

    /**
     * Process a loan-out: user's player goes to AI team.
     */
    private function processLoanOut(Game $game, GamePlayer $player, Team $destinationTeam): Loan
    {
        $returnDate = $game->getSeasonEndDate();

        $loan = Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $game->team_id,
            'loan_team_id' => $destinationTeam->id,
            'started_at' => $game->current_date,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // Move player to AI team
        $player->update([
            'team_id' => $destinationTeam->id,
            'number' => GamePlayer::nextAvailableNumber($game->id, $destinationTeam->id),
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

        ContractService::clearSquadTrimIfResolved($game);

        return $loan;
    }

    /**
     * Complete all active loans (return players to parent teams).
     * Also clears any active loan searches.
     * Called at season end.
     */
    public function returnAllLoans(Game $game): Collection
    {
        $activeLoans = Loan::with(['gamePlayer.player', 'parentTeam', 'loanTeam'])
            ->where('game_id', $game->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->get();

        foreach ($activeLoans as $loan) {
            $this->returnLoan($loan);
        }

        // Clear any active loan searches
        GamePlayer::where('game_id', $game->id)
            ->where('transfer_status', GamePlayer::TRANSFER_STATUS_LOAN_SEARCH)
            ->update([
                'transfer_status' => null,
                'transfer_listed_at' => null,
            ]);

        return $activeLoans;
    }

    /**
     * Return a single loan - player goes back to parent team.
     */
    private function returnLoan(Loan $loan): void
    {
        $gamePlayer = $loan->gamePlayer;
        $gamePlayer->update([
            'team_id' => $loan->parent_team_id,
            'number' => GamePlayer::nextAvailableNumber($gamePlayer->game_id, $loan->parent_team_id),
        ]);

        $loan->update([
            'status' => Loan::STATUS_COMPLETED,
        ]);
    }

    /**
     * Create a pending loan-in request (user-initiated).
     */
    public function requestLoanIn(Game $game, GamePlayer $player): TransferOffer
    {
        if ($player->team_id === null) {
            throw new \InvalidArgumentException('Cannot loan a free agent — no parent team.');
        }

        return TransferOffer::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $game->team_id,
            'selling_team_id' => $player->team_id,
            'offer_type' => TransferOffer::TYPE_LOAN_IN,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_PENDING,
            'expires_at' => $game->current_date->addDays(30),
            'game_date' => $game->current_date,
        ]);
    }

    /**
     * Get active loans for a game (both in and out).
     */
    public function getActiveLoans(Game $game): array
    {
        $allLoans = Loan::with(['gamePlayer.player', 'parentTeam', 'loanTeam'])
            ->where('game_id', $game->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->get();

        $loansIn = $allLoans->filter(fn ($loan) => $loan->loan_team_id === $game->team_id);
        $loansOut = $allLoans->filter(fn ($loan) => $loan->parent_team_id === $game->team_id);

        return [
            'in' => $loansIn,
            'out' => $loansOut,
        ];
    }

    /**
     * Resolve pending incoming loan requests after the next matchday.
     * Called each matchday; evaluates user loan requests that haven't been resolved yet.
     */
    public function resolveIncomingLoanRequests(Game $game, ScoutingService $scoutingService): Collection
    {
        $pendingLoans = TransferOffer::with(['gamePlayer.player', 'gamePlayer.team'])
            ->where('game_id', $game->id)
            ->where('direction', TransferOffer::DIRECTION_INCOMING)
            ->where('offer_type', TransferOffer::TYPE_LOAN_IN)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereNull('resolved_at')
            ->get();

        $resolvedOffers = collect();

        foreach ($pendingLoans as $offer) {
            $evaluation = $scoutingService->evaluateLoanRequest($offer->gamePlayer, $game);

            if ($evaluation['result'] === 'accepted') {
                if ($game->isTransferWindowOpen()) {
                    $this->completeLoanIn($offer, $game);
                    $resolvedOffers->push([
                        'offer' => $offer->fresh(),
                        'result' => 'accepted',
                        'completed' => true,
                    ]);
                } else {
                    $offer->update([
                        'status' => TransferOffer::STATUS_AGREED,
                        'resolved_at' => $game->current_date,
                    ]);
                    $resolvedOffers->push([
                        'offer' => $offer->fresh(),
                        'result' => 'accepted',
                        'completed' => false,
                    ]);
                }
            } else {
                $offer->update([
                    'status' => TransferOffer::STATUS_REJECTED,
                    'resolved_at' => $game->current_date,
                ]);

                $resolvedOffers->push([
                    'offer' => $offer->fresh(),
                    'result' => 'rejected',
                    'completed' => false,
                ]);
            }
        }

        return $resolvedOffers;
    }

    /**
     * Complete a loan-in (player joins user's team on loan).
     */
    public function completeLoanIn(TransferOffer $offer, Game $game): void
    {
        // Safety net: reject if squad is full
        if (ContractService::isSquadFull($game)) {
            $offer->update(['status' => TransferOffer::STATUS_REJECTED, 'resolved_at' => $game->current_date]);
            return;
        }

        $player = $offer->gamePlayer;
        $parentTeamId = $offer->selling_team_id ?? $player->team_id;

        if ($parentTeamId === null) {
            $offer->update(['status' => TransferOffer::STATUS_REJECTED, 'resolved_at' => $game->current_date]);
            return;
        }

        $returnDate = $game->getSeasonEndDate();

        Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $parentTeamId,
            'loan_team_id' => $game->team_id,
            'started_at' => $game->current_date,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        $player->update([
            'team_id' => $game->team_id,
            'number' => GamePlayer::nextAvailableNumber($game->id, $game->team_id),
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $parentTeamId,
            toTeamId: $game->team_id,
            transferFee: 0,
            type: GameTransfer::TYPE_LOAN,
            season: $game->season,
            window: $this->getCurrentWindow($game),
        );

        $offer->update(['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $game->current_date]);

        // Record the loan salary as a financial transaction
        $parentTeam = Team::find($parentTeamId);
        FinancialTransaction::recordExpense(
            gameId: $game->id,
            category: FinancialTransaction::CATEGORY_LOAN,
            amount: $player->annual_wage,
            description: __('finances.tx_loan_in', [
                'player' => $player->player->name ?? $player->id,
                'team' => $parentTeam->name ?? '',
            ]),
            transactionDate: $game->current_date,
            relatedPlayerId: $player->id,
        );

        // Remove from shortlist to free up scouting slot
        ShortlistedPlayer::removeForPlayer($game->id, $player->id);
    }

    /**
     * Complete a loan-out (user's player goes to AI team).
     */
    public function completeLoanOut(TransferOffer $offer, Game $game): void
    {
        $player = $offer->gamePlayer;
        $destinationTeamId = $offer->offering_team_id;
        $returnDate = $game->getSeasonEndDate();

        Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $game->team_id,
            'loan_team_id' => $destinationTeamId,
            'started_at' => $game->current_date,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        $player->update([
            'team_id' => $destinationTeamId,
            'number' => GamePlayer::nextAvailableNumber($game->id, $destinationTeamId),
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

        GameTransfer::record(
            gameId: $game->id,
            gamePlayerId: $player->id,
            fromTeamId: $game->team_id,
            toTeamId: $destinationTeamId,
            transferFee: 0,
            type: GameTransfer::TYPE_LOAN,
            season: $game->season,
            window: $this->getCurrentWindow($game),
        );

        $offer->update(['status' => TransferOffer::STATUS_COMPLETED, 'resolved_at' => $game->current_date]);

        ContractService::clearSquadTrimIfResolved($game);
    }

    /**
     * Complete a sync-negotiated loan. Calls completeLoanIn if window open,
     * otherwise marks as agreed.
     *
     * @return array{result: string, offer: TransferOffer}
     */
    public function completeSyncLoan(TransferOffer $offer, Game $game): array
    {
        if ($game->isTransferWindowOpen()) {
            $this->completeLoanIn($offer, $game);
            return ['result' => 'accepted', 'offer' => $offer->fresh()];
        }

        $offer->update([
            'status' => TransferOffer::STATUS_AGREED,
            'resolved_at' => $game->current_date,
        ]);
        return ['result' => 'accepted', 'offer' => $offer->fresh()];
    }

    /**
     * Get mood indicator for loan disposition.
     *
     * @return array{label: string, color: string}
     */
    public function getLoanMoodIndicator(float $disposition): array
    {
        if ($disposition >= 0.65) {
            return ['label' => __('transfers.mood_willing_loan'), 'color' => 'green'];
        }
        if ($disposition >= 0.40) {
            return ['label' => __('transfers.mood_open_loan'), 'color' => 'amber'];
        }

        return ['label' => __('transfers.mood_reluctant_loan'), 'color' => 'red'];
    }

    /**
     * Determine the current transfer window type.
     */
    private function getCurrentWindow(Game $game): string
    {
        return $game->isWinterWindowOpen() ? 'winter' : 'summer';
    }
}
