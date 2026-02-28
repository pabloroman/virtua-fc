<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\Team;

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
        $transfers = $metadata['transfers'] ?? [];
        $freeAgentSignings = $metadata['free_agent_signings'] ?? [];
        $window = $metadata['window'] ?? 'summer';

        // Get teams in the user's primary competition
        $leagueTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->pluck('team_id')
            ->toArray();

        $competitionName = Competition::where('id', $game->competition_id)->value('name') ?? '';

        // Split transfers by league relevance (fromTeamId OR toTeamId in league)
        $restOfWorldTransfers = [];
        $leagueTeamActivity = [];
        $leagueTransferCount = 0;

        foreach ($transfers as $transfer) {
            $fromInLeague = in_array($transfer['fromTeamId'] ?? null, $leagueTeamIds);
            $toInLeague = in_array($transfer['toTeamId'] ?? null, $leagueTeamIds);

            if ($fromInLeague || $toInLeague) {
                $leagueTransferCount++;

                // Add to selling team's OUT
                if ($fromInLeague && ($transfer['fromTeamId'] ?? null)) {
                    $teamId = $transfer['fromTeamId'];
                    $leagueTeamActivity[$teamId]['out'][] = [
                        'playerName' => $transfer['playerName'],
                        'position' => $transfer['position'] ?? null,
                        'toTeamName' => $transfer['toTeamName'] ?? null,
                        'toTeamId' => $transfer['toTeamId'] ?? null,
                        'formattedFee' => $transfer['formattedFee'],
                        'fee' => $transfer['fee'] ?? 0,
                        'type' => $transfer['type'] ?? 'domestic',
                    ];
                }

                // Add to buying team's IN
                if ($toInLeague && ($transfer['toTeamId'] ?? null)) {
                    $teamId = $transfer['toTeamId'];
                    $leagueTeamActivity[$teamId]['in'][] = [
                        'playerName' => $transfer['playerName'],
                        'position' => $transfer['position'] ?? null,
                        'fromTeamName' => $transfer['fromTeamName'] ?? null,
                        'fromTeamId' => $transfer['fromTeamId'] ?? null,
                        'formattedFee' => $transfer['formattedFee'],
                        'fee' => $transfer['fee'] ?? 0,
                        'type' => $transfer['type'] ?? 'domestic',
                    ];
                }
            } else {
                $restOfWorldTransfers[] = $transfer;
            }
        }

        // Free agent signings → add to buying team's IN
        foreach ($freeAgentSignings as $signing) {
            if (in_array($signing['toTeamId'] ?? null, $leagueTeamIds)) {
                $leagueTransferCount++;
                $teamId = $signing['toTeamId'];
                $leagueTeamActivity[$teamId]['in'][] = [
                    'playerName' => $signing['playerName'],
                    'position' => $signing['position'] ?? null,
                    'fromTeamName' => null,
                    'fromTeamId' => null,
                    'formattedFee' => $signing['formattedFee'],
                    'fee' => 0,
                    'type' => 'free_agent',
                ];
            }
        }

        // Collect all team IDs and load Team models for crests/names
        $allTeamIds = collect(array_merge($transfers, $freeAgentSignings))
            ->flatMap(fn ($t) => array_filter([$t['fromTeamId'] ?? null, $t['toTeamId'] ?? null]))
            ->unique()
            ->values();
        $teams = Team::whereIn('id', $allTeamIds)->get()->keyBy('id');

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

        return view('transfer-activity', [
            'game' => $game,
            'leagueTeamActivity' => $leagueTeamActivity,
            'leagueTransferCount' => $leagueTransferCount,
            'restOfWorldTransfers' => $restOfWorldTransfers,
            'competitionName' => $competitionName,
            'teams' => $teams,
            'window' => $window,
        ]);
    }
}
