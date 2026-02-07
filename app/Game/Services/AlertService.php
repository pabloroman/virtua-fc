<?php

namespace App\Game\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Models\TransferOffer;
use Illuminate\Support\Collection;

class AlertService
{
    private const LOW_FITNESS_THRESHOLD = 70;
    private const YELLOW_CARD_WARNING_THRESHOLD = 4;

    /**
     * Get squad alerts (injuries, suspensions, fitness, card risk).
     */
    public function getSquadAlerts(Game $game, ?GameMatch $nextMatch): array
    {
        $currentDate = $game->current_date;

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
            $this->checkLowFitness($player, $currentDate, $alerts);
            $this->checkYellowCardRisk($player, $alerts);
        }

        $this->checkSuspensions($players, $alerts);
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

    /**
     * Check suspensions for all players (single query, avoids N+1).
     */
    private function checkSuspensions(Collection $players, array &$alerts): void
    {
        $suspensions = PlayerSuspension::whereIn('game_player_id', $players->pluck('id'))
            ->where('matches_remaining', '>', 0)
            ->with('competition')
            ->get();

        $playersById = $players->keyBy('id');

        foreach ($suspensions as $suspension) {
            $player = $playersById->get($suspension->game_player_id);
            if ($player) {
                $alerts['suspended'][] = [
                    'player' => $player,
                    'matchesRemaining' => $suspension->matches_remaining,
                    'competition' => $suspension->competition?->name ?? 'Unknown',
                ];
            }
        }
    }

    private function checkLowFitness(GamePlayer $player, $currentDate, array &$alerts): void
    {
        if ($player->fitness < self::LOW_FITNESS_THRESHOLD &&
            !$player->isInjured($currentDate)) {
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
            'fee' => $offer->isPreContract() ? 'Free Transfer' : $offer->formatted_transfer_fee,
            'daysLeft' => $offer->days_until_expiry,
            'isUnsolicited' => $offer->isUnsolicited(),
            'isPreContract' => $offer->isPreContract(),
        ];
    }
}
