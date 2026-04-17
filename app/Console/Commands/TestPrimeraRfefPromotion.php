<?php

namespace App\Console\Commands;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Enums\PlayoffState;
use App\Modules\Competition\Exceptions\PlayoffInProgressException;
use App\Modules\Competition\Playoffs\PrimeraRFEFPlayoffGenerator;
use App\Modules\Competition\Promotions\PrimeraRFEFPromotionRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Interactive debug command that manufactures Primera RFEF standings and runs
 * the promotion/relegation pipeline — without playing an actual season.
 *
 * Creates a throwaway game with 20+20+22 teams, lets you control which group
 * the player is in (real standings vs simulated sister), triggers the playoff
 * generator, and runs the promotion rule. Prints a detailed report of every
 * step: who got promoted, who got relegated, where relegated teams landed,
 * and final team counts per division.
 */
class TestPrimeraRfefPromotion extends Command
{
    protected $signature = 'app:test-primera-rfef-promotion
                            {--player-group=A : Which group the player is in (A or B)}
                            {--reserve-block : Place a reserve team at position 2 in Group A to test filtering}
                            {--mixed-origins : Make both bracket winners come from the same group (tests uneven redistribution)}
                            {--skip-playoff : Simulate the fallback path where no playoff was played}
                            {--playoff-state=completed : Where to stop in the playoff: not-started, in-progress, or completed}
                            {--cleanup : Delete all test data after the run}';

    protected $description =
        'Debug tool: manufactures Primera RFEF standings and runs the promotion pipeline. ' .
        'Creates a throwaway game — no real season needed.';

    private string $gameId;
    private array $groupATeams = [];
    private array $groupBTeams = [];
    private array $esp2Teams = [];

    public function handle(): int
    {
        $playerGroup = strtoupper($this->option('player-group'));
        if (!in_array($playerGroup, ['A', 'B'])) {
            $this->error('--player-group must be A or B');
            return Command::FAILURE;
        }

        $playoffStateOpt = strtolower($this->option('playoff-state'));
        if (!in_array($playoffStateOpt, ['not-started', 'in-progress', 'completed'])) {
            $this->error('--playoff-state must be not-started, in-progress, or completed');
            return Command::FAILURE;
        }

        $this->info("=== Primera RFEF Promotion Debug Tool ===");
        $this->line("Player group:  ESP3{$playerGroup}");
        $this->line("Reserve block: " . ($this->option('reserve-block') ? 'YES' : 'no'));
        $this->line("Mixed origins: " . ($this->option('mixed-origins') ? 'YES' : 'no'));
        $this->line("Skip playoff:  " . ($this->option('skip-playoff') ? 'YES' : 'no'));
        $this->line("Playoff stop: {$playoffStateOpt}");
        $this->newLine();

        try {
            return DB::transaction(function () use ($playerGroup, $playoffStateOpt) {
                $this->ensureCompetitions();
                $this->createTestGame($playerGroup);
                $this->createTeamsAndStandings($playerGroup);

                $this->reportInitialState();

                // Playoff orchestration driven by --playoff-state:
                //   not-started: skip playoff entirely (expect simulated fallback)
                //   in-progress: generate semis, resolve them, but stop before round 2
                //   completed:   full playoff including finals (default)
                if (!$this->option('skip-playoff') && $playoffStateOpt !== 'not-started') {
                    $this->runPlayoffGenerator();
                    if ($playoffStateOpt === 'completed') {
                        $this->simulatePlayoffResults();
                    } else {
                        $this->simulateSemifinalsOnly();
                    }
                }

                $this->runPromotionRule();
                $this->reportFinalState();

                if ($this->option('cleanup')) {
                    $this->cleanup();
                    $this->line('Test data cleaned up.');
                } else {
                    $this->newLine();
                    $this->warn("Test game ID: {$this->gameId}");
                    $this->warn("Run with --cleanup to auto-delete, or manually: DELETE FROM games WHERE id = '{$this->gameId}'");
                }

                return Command::SUCCESS;
            });
        } catch (\Throwable $e) {
            $this->error("FAILED: {$e->getMessage()}");
            $this->error("  at {$e->getFile()}:{$e->getLine()}");
            $this->newLine();
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function ensureCompetitions(): void
    {
        foreach ([
            ['id' => 'ESP2', 'tier' => 2, 'handler_type' => 'league_with_playoff', 'type' => 'league', 'role' => Competition::ROLE_LEAGUE],
            ['id' => 'ESP3A', 'tier' => 3, 'handler_type' => 'league_with_playoff', 'type' => 'league', 'role' => Competition::ROLE_LEAGUE],
            ['id' => 'ESP3B', 'tier' => 3, 'handler_type' => 'league_with_playoff', 'type' => 'league', 'role' => Competition::ROLE_LEAGUE],
            ['id' => 'ESP3PO', 'tier' => 3, 'handler_type' => 'knockout_cup', 'type' => 'cup', 'role' => Competition::ROLE_DOMESTIC_CUP],
        ] as $comp) {
            Competition::firstOrCreate(['id' => $comp['id']], [
                'name' => $comp['id'],
                'country' => 'ES',
                'tier' => $comp['tier'],
                'type' => $comp['type'],
                'role' => $comp['role'],
                'handler_type' => $comp['handler_type'],
                'scope' => 'domestic',
                'season' => '2025',
            ]);
        }
    }

    private function createTestGame(string $playerGroup): void
    {
        $user = User::first() ?? User::factory()->create();
        $playerTeam = Team::factory()->create(['name' => 'TEST Player Team']);
        $competitionId = $playerGroup === 'A' ? 'ESP3A' : 'ESP3B';

        $game = Game::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'team_id' => $playerTeam->id,
            'competition_id' => $competitionId,
            'season' => '2025',
            'current_date' => '2026-05-20',
            'game_mode' => 'career',
            'country' => 'ES',
        ]);

        $this->gameId = $game->id;

        // Put the player's team at position 3 of the player's group
        if ($playerGroup === 'A') {
            $this->groupATeams[3] = $playerTeam;
        } else {
            $this->groupBTeams[3] = $playerTeam;
        }
    }

    private function createTeamsAndStandings(string $playerGroup): void
    {
        $this->info('Creating teams and standings...');

        // Create ESP2 standings (22 teams)
        $this->esp2Teams = $this->createStandingsForCompetition('ESP2', 22, 'ESP2');

        // Create ESP3A standings or simulation
        if ($playerGroup === 'A') {
            $this->groupATeams = $this->createStandingsForCompetition('ESP3A', 20, 'GrpA', $this->groupATeams);
        } else {
            $this->groupATeams = $this->createSimulatedGroup('ESP3A', 20, 'GrpA');
        }

        // Create ESP3B standings or simulation
        if ($playerGroup === 'B') {
            $this->groupBTeams = $this->createStandingsForCompetition('ESP3B', 20, 'GrpB', $this->groupBTeams);
        } else {
            $this->groupBTeams = $this->createSimulatedGroup('ESP3B', 20, 'GrpB');
        }

        // Reserve team test: place a reserve at position 2 in Group A
        if ($this->option('reserve-block')) {
            $parentInEsp2 = $this->esp2Teams[5]; // arbitrary ESP2 team
            $reserveTeam = $this->getTeamAtPosition('ESP3A', 2, $playerGroup);

            if ($reserveTeam) {
                $reserveTeam->update(['parent_team_id' => $parentInEsp2->id]);
                $this->line("  Reserve block: {$reserveTeam->name} (pos 2, ESP3A) → parent: {$parentInEsp2->name} (ESP2)");
            }
        }
    }

    private function createStandingsForCompetition(string $competitionId, int $count, string $prefix, array $preAssigned = []): array
    {
        $teams = [];
        for ($i = 1; $i <= $count; $i++) {
            $team = $preAssigned[$i] ?? Team::factory()->create(['name' => "{$prefix} Team #{$i}"]);
            $teams[$i] = $team;

            CompetitionEntry::create([
                'game_id' => $this->gameId,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);

            GameStanding::create([
                'game_id' => $this->gameId,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'position' => $i,
                'played' => ($count - 1) * 2,
                'won' => max(0, $count - $i),
                'drawn' => 3,
                'lost' => max(0, $i - 1),
                'goals_for' => max(10, 60 - $i * 2),
                'goals_against' => 20 + $i,
                'points' => max(0, $count - $i) * 3 + 3,
            ]);
        }

        $this->line("  {$competitionId}: {$count} teams with real standings");
        return $teams;
    }

    private function createSimulatedGroup(string $competitionId, int $count, string $prefix): array
    {
        $teams = [];
        $teamIds = [];
        for ($i = 1; $i <= $count; $i++) {
            $team = Team::factory()->create(['name' => "{$prefix} Team #{$i}"]);
            $teams[$i] = $team;
            $teamIds[] = $team->id;

            CompetitionEntry::create([
                'game_id' => $this->gameId,
                'competition_id' => $competitionId,
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        SimulatedSeason::create([
            'game_id' => $this->gameId,
            'season' => '2025',
            'competition_id' => $competitionId,
            'results' => $teamIds,
        ]);

        $this->line("  {$competitionId}: {$count} teams with SIMULATED standings");
        return $teams;
    }

    private function getTeamAtPosition(string $competitionId, int $position, string $playerGroup): ?Team
    {
        $isReal = ($competitionId === 'ESP3A' && $playerGroup === 'A')
               || ($competitionId === 'ESP3B' && $playerGroup === 'B');

        if ($isReal) {
            $standing = GameStanding::where('game_id', $this->gameId)
                ->where('competition_id', $competitionId)
                ->where('position', $position)
                ->first();
            return $standing ? Team::find($standing->team_id) : null;
        }

        $teams = $competitionId === 'ESP3A' ? $this->groupATeams : $this->groupBTeams;
        return $teams[$position] ?? null;
    }

    private function runPlayoffGenerator(): void
    {
        $this->newLine();
        $this->info('=== Running Playoff Generator ===');

        $generator = new PrimeraRFEFPlayoffGenerator();
        $game = Game::find($this->gameId);

        // Round 1: Semifinals
        $this->line('Generating Round 1 (Semifinals)...');
        $matchups = $generator->generateMatchups($game, 1);

        $this->table(
            ['Bracket', 'Home (lower seed)', 'Away (higher seed)'],
            collect($matchups)->map(fn ($m) => [
                $m[2] === PrimeraRFEFPlayoffGenerator::BRACKET_A ? 'A' : 'B',
                $this->teamLabel($m[0]),
                $this->teamLabel($m[1]),
            ])->toArray()
        );

        $entries = CompetitionEntry::where('game_id', $this->gameId)
            ->where('competition_id', 'ESP3PO')
            ->count();
        $this->line("ESP3PO CompetitionEntry count: {$entries}");
    }

    private function simulatePlayoffResults(): void
    {
        $this->newLine();
        $this->info('=== Simulating Playoff Match Results ===');

        $game = Game::find($this->gameId);

        // For round-1 ties: pick the higher-seeded team (away) as winner, unless
        // --mixed-origins is set, in which case both bracket winners come from Group A.
        $round1Ties = CupTie::where('game_id', $this->gameId)
            ->where('competition_id', 'ESP3PO')
            ->where('round_number', 1)
            ->orderBy('bracket_position')
            ->orderBy('id')
            ->get();

        foreach ($round1Ties as $tie) {
            $winner = $tie->away_team_id; // higher seed wins by default
            $tie->update([
                'winner_id' => $winner,
                'completed' => true,
                'resolution' => ['type' => 'aggregate'],
            ]);
            $this->line("  SF: {$this->teamLabel($tie->home_team_id)} vs {$this->teamLabel($tie->away_team_id)} → winner: {$this->teamLabel($winner)}");
        }

        // Generate round 2
        $generator = new PrimeraRFEFPlayoffGenerator();
        $this->line('Generating Round 2 (Bracket Finals)...');
        $matchups = $generator->generateMatchups($game, 2);

        $this->table(
            ['Bracket', 'Home', 'Away'],
            collect($matchups)->map(fn ($m) => [
                $m[2] === PrimeraRFEFPlayoffGenerator::BRACKET_A ? 'A' : 'B',
                $this->teamLabel($m[0]),
                $this->teamLabel($m[1]),
            ])->toArray()
        );

        // Resolve round-2 finals
        $round2Ties = CupTie::where('game_id', $this->gameId)
            ->where('competition_id', 'ESP3PO')
            ->where('round_number', 2)
            ->orderBy('bracket_position')
            ->get();

        foreach ($round2Ties as $index => $tie) {
            if ($this->option('mixed-origins')) {
                // Force both winners to be from Group A by picking away_team in
                // bracket A (which is A2) and home_team in bracket B (which comes
                // from bracket B's first semifinal winner — but we'll just pick
                // whichever side has a Group A team).
                $winner = $this->pickTeamFromGroup($tie, 'ESP3A') ?? $tie->home_team_id;
            } else {
                $winner = $tie->home_team_id; // natural: first semifinal winner
            }

            $tie->update([
                'winner_id' => $winner,
                'completed' => true,
                'resolution' => ['type' => 'aggregate'],
            ]);
            $bracketLabel = $tie->bracket_position === PrimeraRFEFPlayoffGenerator::BRACKET_A ? 'A' : 'B';
            $this->line("  Final {$bracketLabel}: {$this->teamLabel($tie->home_team_id)} vs {$this->teamLabel($tie->away_team_id)} → winner: {$this->teamLabel($winner)}");
        }

        $this->line('Playoff complete: ' . ($generator->isComplete($game) ? 'YES' : 'NO'));
    }

    /**
     * Resolve all semifinal CupTies but DO NOT generate the bracket finals.
     * Used to exercise the PlayoffState::InProgress path — the guard should
     * refuse promotion until the finals are played.
     */
    private function simulateSemifinalsOnly(): void
    {
        $this->newLine();
        $this->info('=== Simulating Semifinals Only (InProgress state) ===');

        $round1Ties = CupTie::where('game_id', $this->gameId)
            ->where('competition_id', 'ESP3PO')
            ->where('round_number', 1)
            ->orderBy('bracket_position')
            ->orderBy('id')
            ->get();

        foreach ($round1Ties as $tie) {
            $winner = $tie->away_team_id;
            $tie->update([
                'winner_id' => $winner,
                'completed' => true,
                'resolution' => ['type' => 'aggregate'],
            ]);
        }

        $generator = new PrimeraRFEFPlayoffGenerator();
        $game = Game::find($this->gameId);
        $this->line('Playoff state: ' . $generator->state($game)->value);
    }

    private function runPromotionRule(): void
    {
        $this->newLine();
        $this->info('=== Running Promotion Rule ===');

        $rule = new PrimeraRFEFPromotionRule();
        $game = Game::find($this->gameId);

        try {
            $promoted = $rule->getPromotedTeams($game);
            $relegated = $rule->getRelegatedTeams($game);
        } catch (PlayoffInProgressException $e) {
            $this->warn('PlayoffInProgressException thrown — this is the CORRECT behaviour when the');
            $this->warn('playoff has not finished. The season transition must refuse to run here.');
            $this->line("  Message: {$e->getMessage()}");
            return;
        }

        $this->line("Promoted: " . count($promoted) . " teams");
        $this->table(
            ['Team', 'Position', 'Origin'],
            collect($promoted)->map(fn ($p) => [
                $this->teamLabel($p['teamId']),
                $p['position'],
                $p['origin'] ?? '—',
            ])->toArray()
        );

        $this->line("Relegated: " . count($relegated) . " teams");
        $this->table(
            ['Team', 'Position'],
            collect($relegated)->map(fn ($r) => [
                $this->teamLabel($r['teamId']),
                $r['position'],
            ])->toArray()
        );

        if (count($promoted) !== count($relegated)) {
            $this->error("IMBALANCE: {$promoted} promoted vs {$relegated} relegated!");
            return;
        }

        $this->newLine();
        $this->info('Performing swap...');
        $rule->performSwap($game, $promoted, $relegated);
        $this->line('Swap completed.');
    }

    private function reportInitialState(): void
    {
        $this->newLine();
        $this->info('=== Initial State ===');
        $this->reportDivisionCounts();
    }

    private function reportFinalState(): void
    {
        $this->newLine();
        $this->info('=== Final State (after promotion/relegation) ===');
        $this->reportDivisionCounts();

        $game = Game::find($this->gameId);
        $this->line("Player's competition_id: {$game->competition_id}");

        // Check ESP3PO was cleared
        $esp3poCupTies = CupTie::where('game_id', $this->gameId)->where('competition_id', 'ESP3PO')->count();
        $esp3poEntries = CompetitionEntry::where('game_id', $this->gameId)->where('competition_id', 'ESP3PO')->count();
        $this->line("ESP3PO cleanup: {$esp3poCupTies} cup ties, {$esp3poEntries} entries (both should be 0)");
    }

    private function reportDivisionCounts(): void
    {
        foreach (['ESP2', 'ESP3A', 'ESP3B', 'ESP3PO'] as $compId) {
            $entries = CompetitionEntry::where('game_id', $this->gameId)
                ->where('competition_id', $compId)
                ->count();
            $standings = GameStanding::where('game_id', $this->gameId)
                ->where('competition_id', $compId)
                ->count();
            $this->line("  {$compId}: {$entries} entries, {$standings} standings");
        }
    }

    private function teamLabel(string $teamId): string
    {
        $team = Team::find($teamId);
        return $team ? $team->name : substr($teamId, 0, 8);
    }

    private function pickTeamFromGroup(CupTie $tie, string $groupId): ?string
    {
        foreach ([$tie->home_team_id, $tie->away_team_id] as $teamId) {
            $inGroup = CompetitionEntry::where('game_id', $this->gameId)
                ->where('competition_id', $groupId)
                ->where('team_id', $teamId)
                ->exists();
            if ($inGroup) {
                return $teamId;
            }
        }
        return null;
    }

    private function cleanup(): void
    {
        CupTie::where('game_id', $this->gameId)->delete();
        GameMatch::where('game_id', $this->gameId)->delete();
        GameStanding::where('game_id', $this->gameId)->delete();
        CompetitionEntry::where('game_id', $this->gameId)->delete();
        SimulatedSeason::where('game_id', $this->gameId)->delete();
        Game::where('id', $this->gameId)->delete();
    }
}
