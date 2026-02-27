<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\TransferOffer;
use Illuminate\Console\Command;

class SeedTransferData extends Command
{
    protected $signature = 'app:seed-transfer-data
                            {game_id : The game UUID to seed transfer data for}
                            {--fresh : Delete existing transfer_offer records for this game first}';

    protected $description = 'Seed dummy transfer_offer records for testing the transfers page';

    public function handle(): int
    {
        $game = Game::with('team')->find($this->argument('game_id'));

        if (! $game) {
            $this->error('Game not found: ' . $this->argument('game_id'));
            return Command::FAILURE;
        }

        $this->info("Seeding transfer data for {$game->team->name} (game date: {$game->current_date->toDateString()})");

        if ($this->option('fresh')) {
            $deleted = TransferOffer::where('game_id', $game->id)->delete();
            $this->line("  Deleted $deleted existing transfer_offer records.");
        }

        $myTeamId = $game->team_id;

        // Get 8 of the user's players
        $myPlayers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $myTeamId)
            ->limit(8)
            ->pluck('id')
            ->toArray();

        if (count($myPlayers) < 8) {
            $this->error("Not enough players in user's squad (found " . count($myPlayers) . ', need 8).');
            return Command::FAILURE;
        }

        // Pick one rival team and grab 8 of their players
        $rivalTeamId = GamePlayer::where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->where('team_id', '!=', $myTeamId)
            ->value('team_id');

        if (! $rivalTeamId) {
            $this->error('No rival players found in this game.');
            return Command::FAILURE;
        }

        $rivalPlayers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $rivalTeamId)
            ->limit(8)
            ->pluck('id')
            ->toArray();

        // Grab 8 AI clubs to act as the offering teams
        $offeringTeams = Team::where('id', '!=', $myTeamId)
            ->where('id', '!=', $rivalTeamId)
            ->limit(8)
            ->pluck('id')
            ->toArray();

        if (count($offeringTeams) < 8) {
            $this->error('Not enough teams in the database.');
            return Command::FAILURE;
        }

        $gameDate = $game->current_date->toDateString();
        $futureExpiry = $game->current_date->addDays(14)->toDateString();
        $pastExpiry   = $game->current_date->subDays(7)->toDateString();
        $pastDate     = $game->current_date->subDays(21)->toDateString();

        $base = [
            'game_id'   => $game->id,
            'game_date' => $gameDate,
            'expires_at' => $futureExpiry,
        ];

        $records = [

            // ─── UNSOLICITED (AI poaching user's player, not listed) ──────────────
            // pending ×2
            $base + ['game_player_id' => $myPlayers[0], 'offering_team_id' => $offeringTeams[0], 'offer_type' => 'unsolicited', 'status' => 'pending',   'direction' => 'outgoing', 'transfer_fee' => 800_000_000],
            $base + ['game_player_id' => $myPlayers[1], 'offering_team_id' => $offeringTeams[1], 'offer_type' => 'unsolicited', 'status' => 'pending',   'direction' => 'outgoing', 'transfer_fee' => 500_000_000],
            // agreed
            $base + ['game_player_id' => $myPlayers[2], 'offering_team_id' => $offeringTeams[2], 'offer_type' => 'unsolicited', 'status' => 'agreed',    'direction' => 'outgoing', 'transfer_fee' => 1_200_000_000, 'resolved_at' => null],
            // rejected
            $base + ['game_player_id' => $myPlayers[3], 'offering_team_id' => $offeringTeams[3], 'offer_type' => 'unsolicited', 'status' => 'rejected',  'direction' => 'outgoing', 'transfer_fee' => 300_000_000, 'resolved_at' => $gameDate],
            // expired
            ['game_id' => $game->id, 'game_player_id' => $myPlayers[4], 'offering_team_id' => $offeringTeams[4], 'offer_type' => 'unsolicited', 'status' => 'expired',   'direction' => 'outgoing', 'transfer_fee' => 200_000_000, 'expires_at' => $pastExpiry,   'game_date' => $pastDate],
            // completed
            ['game_id' => $game->id, 'game_player_id' => $myPlayers[5], 'offering_team_id' => $offeringTeams[5], 'offer_type' => 'unsolicited', 'status' => 'completed', 'direction' => 'outgoing', 'transfer_fee' => 1_500_000_000, 'expires_at' => $pastExpiry, 'game_date' => $pastDate, 'resolved_at' => $pastDate],

            // ─── LISTED (user put player on transfer list, AI club offers) ────────
            // pending ×2
            $base + ['game_player_id' => $myPlayers[0], 'offering_team_id' => $offeringTeams[6], 'offer_type' => 'listed', 'status' => 'pending',   'direction' => 'outgoing', 'transfer_fee' => 700_000_000],
            $base + ['game_player_id' => $myPlayers[6], 'offering_team_id' => $offeringTeams[7], 'offer_type' => 'listed', 'status' => 'pending',   'direction' => 'outgoing', 'transfer_fee' => 450_000_000],
            // agreed
            $base + ['game_player_id' => $myPlayers[7], 'offering_team_id' => $offeringTeams[0], 'offer_type' => 'listed', 'status' => 'agreed',    'direction' => 'outgoing', 'transfer_fee' => 900_000_000, 'resolved_at' => null],
            // rejected
            $base + ['game_player_id' => $myPlayers[1], 'offering_team_id' => $offeringTeams[1], 'offer_type' => 'listed', 'status' => 'rejected',  'direction' => 'outgoing', 'transfer_fee' => 250_000_000, 'resolved_at' => $gameDate],
            // expired
            ['game_id' => $game->id, 'game_player_id' => $myPlayers[2], 'offering_team_id' => $offeringTeams[2], 'offer_type' => 'listed', 'status' => 'expired',   'direction' => 'outgoing', 'transfer_fee' => 180_000_000, 'expires_at' => $pastExpiry,   'game_date' => $pastDate],
            // completed
            ['game_id' => $game->id, 'game_player_id' => $myPlayers[3], 'offering_team_id' => $offeringTeams[3], 'offer_type' => 'listed', 'status' => 'completed', 'direction' => 'outgoing', 'transfer_fee' => 2_000_000_000, 'expires_at' => $pastExpiry, 'game_date' => $pastDate, 'resolved_at' => $pastDate],

            // ─── PRE-CONTRACT (user's expiring player, rival offers free transfer) ─
            // pending ×2
            $base + ['game_player_id' => $myPlayers[4], 'offering_team_id' => $offeringTeams[4], 'offer_type' => 'pre_contract', 'status' => 'pending',   'direction' => 'outgoing', 'transfer_fee' => 0, 'offered_wage' => 10_000_000],
            $base + ['game_player_id' => $myPlayers[5], 'offering_team_id' => $offeringTeams[5], 'offer_type' => 'pre_contract', 'status' => 'pending',   'direction' => 'outgoing', 'transfer_fee' => 0, 'offered_wage' => 15_000_000],
            // agreed
            $base + ['game_player_id' => $myPlayers[6], 'offering_team_id' => $offeringTeams[6], 'offer_type' => 'pre_contract', 'status' => 'agreed',    'direction' => 'outgoing', 'transfer_fee' => 0, 'offered_wage' => 18_000_000, 'resolved_at' => null],
            // completed
            ['game_id' => $game->id, 'game_player_id' => $myPlayers[7], 'offering_team_id' => $offeringTeams[7], 'offer_type' => 'pre_contract', 'status' => 'completed', 'direction' => 'outgoing', 'transfer_fee' => 0, 'offered_wage' => 12_000_000, 'expires_at' => $pastExpiry, 'game_date' => $pastDate, 'resolved_at' => $pastDate],

            // ─── LOAN OUT (user lending player to AI club) ────────────────────────
            $base + ['game_player_id' => $myPlayers[0], 'offering_team_id' => $offeringTeams[0], 'offer_type' => 'loan_out', 'status' => 'pending',   'direction' => 'outgoing', 'transfer_fee' => 100_000_000],
            $base + ['game_player_id' => $myPlayers[1], 'offering_team_id' => $offeringTeams[1], 'offer_type' => 'loan_out', 'status' => 'agreed',    'direction' => 'outgoing', 'transfer_fee' =>  50_000_000, 'resolved_at' => null],
            ['game_id' => $game->id, 'game_player_id' => $myPlayers[2], 'offering_team_id' => $offeringTeams[2], 'offer_type' => 'loan_out', 'status' => 'completed', 'direction' => 'outgoing', 'transfer_fee' => 50_000_000, 'expires_at' => $pastExpiry, 'game_date' => $pastDate, 'resolved_at' => $pastDate],
            $base + ['game_player_id' => $myPlayers[3], 'offering_team_id' => $offeringTeams[3], 'offer_type' => 'loan_out', 'status' => 'rejected',  'direction' => 'outgoing', 'transfer_fee' =>           0, 'resolved_at' => $gameDate],

            // ─── USER BID (incoming: user buying a player from an AI club) ────────
            // pending with counter-offer (asking_price > transfer_fee → badge on tab)
            $base + ['game_player_id' => $rivalPlayers[0], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'user_bid', 'status' => 'pending',   'direction' => 'incoming', 'transfer_fee' => 500_000_000, 'asking_price' => 800_000_000, 'offered_wage' => 10_000_000],
            // pending where bid matches asking (no counter-offer)
            $base + ['game_player_id' => $rivalPlayers[1], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'user_bid', 'status' => 'pending',   'direction' => 'incoming', 'transfer_fee' => 1_000_000_000, 'asking_price' => 900_000_000, 'offered_wage' => 15_000_000],
            // agreed
            $base + ['game_player_id' => $rivalPlayers[2], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'user_bid', 'status' => 'agreed',    'direction' => 'incoming', 'transfer_fee' => 700_000_000, 'asking_price' => 700_000_000, 'offered_wage' => 12_000_000, 'resolved_at' => null],
            // rejected
            $base + ['game_player_id' => $rivalPlayers[3], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'user_bid', 'status' => 'rejected',  'direction' => 'incoming', 'transfer_fee' => 200_000_000, 'asking_price' => 600_000_000, 'offered_wage' =>  8_000_000, 'resolved_at' => $gameDate],
            // expired
            ['game_id' => $game->id, 'game_player_id' => $rivalPlayers[4], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'user_bid', 'status' => 'expired',   'direction' => 'incoming', 'transfer_fee' => 400_000_000, 'asking_price' => 700_000_000, 'offered_wage' =>  9_000_000, 'expires_at' => $pastExpiry, 'game_date' => $pastDate],
            // completed
            ['game_id' => $game->id, 'game_player_id' => $rivalPlayers[5], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'user_bid', 'status' => 'completed', 'direction' => 'incoming', 'transfer_fee' => 900_000_000, 'asking_price' => 900_000_000, 'offered_wage' => 14_000_000, 'expires_at' => $pastExpiry, 'game_date' => $pastDate, 'resolved_at' => $pastDate],

            // ─── LOAN IN (incoming: user borrowing a player from an AI club) ──────
            $base + ['game_player_id' => $rivalPlayers[6], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'loan_in', 'status' => 'pending',   'direction' => 'incoming', 'transfer_fee' =>  50_000_000, 'offered_wage' => 8_000_000],
            $base + ['game_player_id' => $rivalPlayers[7], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'loan_in', 'status' => 'agreed',    'direction' => 'incoming', 'transfer_fee' =>           0, 'offered_wage' => 8_000_000, 'resolved_at' => null],
            $base + ['game_player_id' => $rivalPlayers[0], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'loan_in', 'status' => 'rejected',  'direction' => 'incoming', 'transfer_fee' =>           0, 'offered_wage' => 7_000_000, 'resolved_at' => $gameDate],
            ['game_id' => $game->id, 'game_player_id' => $rivalPlayers[1], 'offering_team_id' => $myTeamId, 'selling_team_id' => $rivalTeamId, 'offer_type' => 'loan_in', 'status' => 'completed', 'direction' => 'incoming', 'transfer_fee' =>           0, 'offered_wage' => 9_000_000, 'expires_at' => $pastExpiry, 'game_date' => $pastDate, 'resolved_at' => $pastDate],
        ];

        foreach ($records as $row) {
            TransferOffer::create($row);
        }

        $this->info('  Inserted ' . count($records) . ' transfer_offer records.');

        // Mark two of the user's players as listed so the "listed players" block shows
        GamePlayer::whereIn('id', [$myPlayers[0], $myPlayers[6]])
            ->update(['transfer_status' => GamePlayer::TRANSFER_STATUS_LISTED]);

        $this->info('  Set transfer_status=listed on 2 players.');
        $this->newLine();
        $this->line('Summary of records created:');
        $this->table(
            ['Type', 'Direction', 'Status', 'Count'],
            collect($records)
                ->groupBy(fn ($r) => "{$r['offer_type']}|{$r['direction']}|{$r['status']}")
                ->map(fn ($group, $key) => array_merge(explode('|', $key), [count($group)]))
                ->values()
                ->toArray()
        );

        return Command::SUCCESS;
    }
}
