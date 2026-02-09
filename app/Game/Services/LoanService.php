<?php

namespace App\Game\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
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
    public function findBestDestination(Game $game, GamePlayer $player): ?Team
    {
        $teams = Team::with(['clubProfile', 'competitions'])
            ->whereHas('competitions', function ($q) {
                $q->where('type', 'league');
            })
            ->where('id', '!=', $game->team_id)
            ->get();

        if ($teams->isEmpty()) {
            return null;
        }

        // Score each team
        $scored = $teams->map(function (Team $team) use ($game, $player) {
            return [
                'team' => $team,
                'score' => $this->scoreLoanDestination($game, $player, $team),
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
     */
    private function scoreLoanDestination(Game $game, GamePlayer $player, Team $team): int
    {
        $score = 0;

        // Reputation match (0-40 pts)
        $score += $this->scoreReputation($player, $team);

        // Position need (0-30 pts)
        $score += $this->scorePositionNeed($game, $player, $team);

        // League tier (0-20 pts)
        $score += $this->scoreLeagueTier($player, $team);

        // Random variety (0-10 pts)
        $score += rand(0, 10);

        return $score;
    }

    /**
     * Score reputation match (0-40 pts).
     */
    private function scoreReputation(GamePlayer $player, Team $team): int
    {
        $expectedReputation = $this->getExpectedReputation($player);
        $teamReputation = $team->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_MODEST;

        $tiers = [
            ClubProfile::REPUTATION_ELITE,
            ClubProfile::REPUTATION_CONTENDERS,
            ClubProfile::REPUTATION_CONTINENTAL,
            ClubProfile::REPUTATION_ESTABLISHED,
            ClubProfile::REPUTATION_MODEST,
            ClubProfile::REPUTATION_PROFESSIONAL,
            ClubProfile::REPUTATION_LOCAL,
        ];

        $expectedIndex = array_search($expectedReputation, $tiers);
        $teamIndex = array_search($teamReputation, $tiers);

        if ($expectedIndex === false || $teamIndex === false) {
            return 15;
        }

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
            return ClubProfile::REPUTATION_CONTENDERS;
        }
        if ($avgAbility >= 70) {
            return ClubProfile::REPUTATION_CONTINENTAL;
        }
        if ($avgAbility >= 64) {
            return ClubProfile::REPUTATION_ESTABLISHED;
        }
        if ($avgAbility >= 55) {
            return ClubProfile::REPUTATION_MODEST;
        }

        return ClubProfile::REPUTATION_LOCAL;
    }

    /**
     * Score position need (0-30 pts).
     */
    private function scorePositionNeed(Game $game, GamePlayer $player, Team $team): int
    {
        $positionGroup = $player->position_group;

        $count = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->get()
            ->filter(fn ($p) => $p->position_group === $positionGroup)
            ->count();

        return match (true) {
            $count <= 1 => 30,
            $count === 2 => 20,
            $count === 3 => 10,
            default => 0,
        };
    }

    /**
     * Score league tier preference (0-20 pts).
     * Uses team reputation level instead of hardcoded competition IDs.
     */
    private function scoreLeagueTier(GamePlayer $player, Team $team): int
    {
        $reputation = $team->clubProfile?->reputation_level ?? ClubProfile::REPUTATION_MODEST;
        $devStatus = $player->development_status;
        $avgAbility = (int) round(($player->current_technical_ability + $player->current_physical_ability) / 2);

        $isSmallClub = in_array($reputation, [
            ClubProfile::REPUTATION_MODEST,
            ClubProfile::REPUTATION_PROFESSIONAL,
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
     * Process a loan-in: player joins user's team on loan.
     */
    public function processLoanIn(Game $game, GamePlayer $player): Loan
    {
        $parentTeamId = $player->team_id;
        $returnDate = $game->getSeasonEndDate();

        $loan = Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $parentTeamId,
            'loan_team_id' => $game->team_id,
            'started_at' => $game->current_date,
            'return_at' => $returnDate,
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // Move player to user's team
        $player->update([
            'team_id' => $game->team_id,
            'joined_on' => $game->current_date,
        ]);

        return $loan;
    }

    /**
     * Process a loan-out: user's player goes to AI team.
     */
    public function processLoanOut(Game $game, GamePlayer $player, Team $destinationTeam): Loan
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
            'transfer_status' => null,
            'transfer_listed_at' => null,
        ]);

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
        $loan->gamePlayer->update([
            'team_id' => $loan->parent_team_id,
        ]);

        $loan->update([
            'status' => Loan::STATUS_COMPLETED,
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
}
