<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Support\Money;

class ShowTransferActivity
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // Find the most recent AI transfer activity notification
        $notification = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_AI_TRANSFER_ACTIVITY)
            ->orderByDesc('game_date')
            ->first();

        if (! $notification) {
            return redirect()->route('show-game', $gameId);
        }

        // Mark the notification as read
        $notification->markAsRead();

        $metadata = $notification->metadata ?? [];
        $window = $metadata['window'] ?? 'summer';
        $season = $metadata['season'] ?? $game->season;

        // Query all transfers for this window from the ledger
        $transfers = GameTransfer::with(['gamePlayer.player', 'fromTeam', 'toTeam'])
            ->where('game_id', $game->id)
            ->where('season', $season)
            ->where('window', $window)
            ->get();

        // Get teams in the user's primary competition
        $leagueTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->pluck('team_id')
            ->toArray();

        $competitionName = Competition::where('id', $game->competition_id)->value('name') ?? '';

        // Collect all team IDs and load Team models for crests/names
        $allTeamIds = $transfers
            ->flatMap(fn ($t) => array_filter([$t->from_team_id, $t->to_team_id]))
            ->unique()
            ->values();
        $teams = Team::whereIn('id', $allTeamIds)->get()->keyBy('id');

        // Split transfers by league relevance
        $leagueTeamActivity = [];
        $leagueTransferCount = 0;
        $restOfWorldTransfers = [];

        foreach ($transfers as $transfer) {
            $fromInLeague = in_array($transfer->from_team_id, $leagueTeamIds);
            $toInLeague = in_array($transfer->to_team_id, $leagueTeamIds);
            $isFreeAgent = $transfer->type === GameTransfer::TYPE_FREE_AGENT;

            $playerName = $transfer->gamePlayer->name ?? 'Unknown';
            $position = $transfer->gamePlayer->position ?? null;
            $fee = $transfer->transfer_fee;
            $formattedFee = $isFreeAgent ? __('transfers.free_transfer') : Money::format($fee);

            $type = $isFreeAgent ? 'free_agent' : 'transfer';

            $fromTeamName = $transfer->fromTeam?->name;
            $toTeamName = $transfer->toTeam?->name;

            if ($fromInLeague || $toInLeague) {
                $leagueTransferCount++;

                // Add to selling team's OUT (skip free agents — they have no from team)
                if ($fromInLeague && $transfer->from_team_id) {
                    $teamId = $transfer->from_team_id;
                    $leagueTeamActivity[$teamId]['out'][] = [
                        'playerName' => $playerName,
                        'position' => $position,
                        'toTeamName' => $toTeamName,
                        'toTeamId' => $transfer->to_team_id,
                        'formattedFee' => $formattedFee,
                        'fee' => $fee,
                        'type' => $type,
                    ];
                }

                // Add to buying team's IN
                if ($toInLeague && $transfer->to_team_id) {
                    $teamId = $transfer->to_team_id;
                    $leagueTeamActivity[$teamId]['in'][] = [
                        'playerName' => $playerName,
                        'position' => $position,
                        'fromTeamName' => $fromTeamName,
                        'fromTeamId' => $transfer->from_team_id,
                        'formattedFee' => $formattedFee,
                        'fee' => $fee,
                        'type' => $type,
                    ];
                }
            } else {
                $restOfWorldTransfers[] = [
                    'playerName' => $playerName,
                    'position' => $position,
                    'fromTeamId' => $transfer->from_team_id,
                    'fromTeamName' => $fromTeamName,
                    'toTeamId' => $transfer->to_team_id,
                    'toTeamName' => $toTeamName,
                    'fee' => $fee,
                    'formattedFee' => $formattedFee,
                    'type' => $type,
                ];
            }
        }

        // Fill in team names + sort each team's transfers
        foreach ($leagueTeamActivity as $teamId => &$activity) {
            $team = $teams->get($teamId);
            $activity['teamId'] = $teamId;
            $activity['teamName'] = $team?->name ?? 'Unknown';
            $activity['in'] = $activity['in'] ?? [];
            $activity['out'] = $activity['out'] ?? [];

            // Sort OUT by fee descending
            usort($activity['out'], fn ($a, $b) => $b['fee'] <=> $a['fee']);
            // Sort IN by fee descending (free agents naturally go last with fee=0)
            usort($activity['in'], fn ($a, $b) => $b['fee'] <=> $a['fee']);
        }
        unset($activity);

        // Sort teams alphabetically by name
        uasort($leagueTeamActivity, fn ($a, $b) => strcasecmp($a['teamName'], $b['teamName']));

        // Sort rest-of-world by fee descending, cap at 20
        usort($restOfWorldTransfers, fn ($a, $b) => ($b['fee'] ?? 0) <=> ($a['fee'] ?? 0));
        $restOfWorldTransfers = array_slice($restOfWorldTransfers, 0, 20);

        // Group rest-of-world transfers by team (same structure as league)
        $restOfWorldTeamActivity = [];
        foreach ($restOfWorldTransfers as $transfer) {
            $fromId = $transfer['fromTeamId'] ?? null;
            $toId = $transfer['toTeamId'] ?? null;

            if ($fromId) {
                $restOfWorldTeamActivity[$fromId]['out'][] = [
                    'playerName' => $transfer['playerName'],
                    'position' => $transfer['position'] ?? null,
                    'toTeamName' => $transfer['toTeamName'] ?? null,
                    'toTeamId' => $toId,
                    'formattedFee' => $transfer['formattedFee'],
                    'fee' => $transfer['fee'] ?? 0,
                    'type' => $transfer['type'] ?? 'transfer',
                ];
            }

            if ($toId) {
                $restOfWorldTeamActivity[$toId]['in'][] = [
                    'playerName' => $transfer['playerName'],
                    'position' => $transfer['position'] ?? null,
                    'fromTeamName' => $transfer['fromTeamName'] ?? null,
                    'fromTeamId' => $fromId,
                    'formattedFee' => $transfer['formattedFee'],
                    'fee' => $transfer['fee'] ?? 0,
                    'type' => $transfer['type'] ?? 'transfer',
                ];
            }
        }

        foreach ($restOfWorldTeamActivity as $teamId => &$activity) {
            $team = $teams->get($teamId);
            $activity['teamId'] = $teamId;
            $activity['teamName'] = $team?->name ?? 'Unknown';
            $activity['in'] = $activity['in'] ?? [];
            $activity['out'] = $activity['out'] ?? [];
            usort($activity['out'], fn ($a, $b) => $b['fee'] <=> $a['fee']);
            usort($activity['in'], fn ($a, $b) => $b['fee'] <=> $a['fee']);
        }
        unset($activity);

        uasort($restOfWorldTeamActivity, fn ($a, $b) => strcasecmp($a['teamName'], $b['teamName']));

        return view('transfer-activity', [
            'game' => $game,
            'leagueTeamActivity' => $leagueTeamActivity,
            'leagueTransferCount' => $leagueTransferCount,
            'restOfWorldTeamActivity' => $restOfWorldTeamActivity,
            'restOfWorldCount' => count($restOfWorldTransfers),
            'competitionName' => $competitionName,
            'teams' => $teams,
            'window' => $window,
        ]);
    }
}
