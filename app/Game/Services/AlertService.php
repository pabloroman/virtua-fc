<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\TransferOffer;

class AlertService
{
    private const LOW_FITNESS_THRESHOLD = 70;
    private const YELLOW_CARD_WARNING_THRESHOLD = 4;

    /**
     * Get all alerts for the dashboard.
     */
    public function getDashboardAlerts(Game $game, ?GameMatch $nextMatch): array
    {
        return [
            'squad' => $this->getSquadAlerts($game, $nextMatch),
            'transfer' => $this->getTransferAlerts($game),
        ];
    }

    /**
     * Get squad alerts (injuries, suspensions, fitness, card risk).
     */
    public function getSquadAlerts(Game $game, ?GameMatch $nextMatch): array
    {
        $currentDate = $game->current_date;
        $nextMatchday = $nextMatch?->round_number ?? $game->current_matchday + 1;

        $players = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        $alerts = [
            'injured' => [],
            'suspended' => [],
            'lowFitness' => [],
            'yellowCardRisk' => [],
        ];

        foreach ($players as $player) {
            $this->checkInjury($player, $currentDate, $alerts);
            $this->checkSuspension($player, $nextMatchday, $alerts);
            $this->checkLowFitness($player, $currentDate, $nextMatchday, $alerts);
            $this->checkYellowCardRisk($player, $alerts);
        }

        $this->sortAlertsByUrgency($alerts);

        return $alerts;
    }

    /**
     * Get transfer alerts (new offers, expiring offers).
     */
    public function getTransferAlerts(Game $game): array
    {
        $alerts = [
            'newOffers' => [],
            'expiringOffers' => [],
        ];

        $currentDate = $game->current_date;

        $pendingOffers = TransferOffer::with(['gamePlayer.player', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_PENDING)
            ->whereHas('gamePlayer', function ($query) use ($game) {
                $query->where('team_id', $game->team_id);
            })
            ->where('expires_at', '>=', $currentDate)
            ->orderByDesc('transfer_fee')
            ->get();

        foreach ($pendingOffers as $offer) {
            $alertData = $this->buildOfferAlertData($offer);

            if ($alertData['daysLeft'] <= 2) {
                $alerts['expiringOffers'][] = $alertData;
            } elseif ($offer->created_at->toDateString() === $currentDate->toDateString()) {
                $alerts['newOffers'][] = $alertData;
            }
        }

        return $alerts;
    }

    private function checkInjury(GamePlayer $player, $currentDate, array &$alerts): void
    {
        if ($player->injury_until && $player->injury_until->gt($currentDate)) {
            $alerts['injured'][] = [
                'player' => $player,
                'reason' => $player->injury_type ?? 'Injury',
                'returnDate' => $player->injury_until,
                'daysRemaining' => $currentDate->diffInDays($player->injury_until),
            ];
        }
    }

    private function checkSuspension(GamePlayer $player, int $nextMatchday, array &$alerts): void
    {
        if ($player->suspended_until_matchday && $player->suspended_until_matchday > $nextMatchday) {
            $alerts['suspended'][] = [
                'player' => $player,
                'matchesRemaining' => $player->suspended_until_matchday - $nextMatchday,
            ];
        }
    }

    private function checkLowFitness(GamePlayer $player, $currentDate, int $nextMatchday, array &$alerts): void
    {
        if ($player->fitness < self::LOW_FITNESS_THRESHOLD &&
            !$player->isInjured($currentDate) &&
            !$player->isSuspended($nextMatchday)) {
            $alerts['lowFitness'][] = [
                'player' => $player,
                'fitness' => $player->fitness,
            ];
        }
    }

    private function checkYellowCardRisk(GamePlayer $player, array &$alerts): void
    {
        if ($player->yellow_cards == self::YELLOW_CARD_WARNING_THRESHOLD) {
            $alerts['yellowCardRisk'][] = [
                'player' => $player,
                'yellowCards' => $player->yellow_cards,
            ];
        }
    }

    private function sortAlertsByUrgency(array &$alerts): void
    {
        usort($alerts['injured'], fn($a, $b) => $a['daysRemaining'] <=> $b['daysRemaining']);
        usort($alerts['lowFitness'], fn($a, $b) => $a['fitness'] <=> $b['fitness']);
        usort($alerts['yellowCardRisk'], fn($a, $b) => $b['yellowCards'] <=> $a['yellowCards']);
    }

    private function buildOfferAlertData(TransferOffer $offer): array
    {
        return [
            'offer' => $offer,
            'playerName' => $offer->gamePlayer->player->name,
            'teamName' => $offer->offeringTeam->name,
            'fee' => $offer->formatted_transfer_fee,
            'daysLeft' => $offer->days_until_expiry,
            'isUnsolicited' => $offer->isUnsolicited(),
        ];
    }
}
