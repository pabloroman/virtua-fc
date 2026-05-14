<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AIPreemptiveRenewalProcessor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIPreemptiveRenewalProcessorTest extends TestCase
{
    use RefreshDatabase;

    private AIPreemptiveRenewalProcessor $processor;
    private Team $userTeam;
    private Team $aiTeam;
    private Game $game;

    /** Contract end matching the new-season cutoff used in the test transition data. */
    private const EXPIRING_CONTRACT = '2027-06-30';
    /** Renewed contracts get extended by 3 seasons. */
    private const RENEWED_CONTRACT = '2029-06-30';

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = app(AIPreemptiveRenewalProcessor::class);

        $this->userTeam = Team::factory()->create();
        $this->aiTeam = Team::factory()->create();

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    public function test_renews_top_importance_players_in_majority_of_runs(): void
    {
        // A 5-player AI squad with one clear best player (top importance) and
        // one clear worst (bottom importance). Run the processor many times to
        // sample around the per-bucket probabilities.
        $renewedTopCount = 0;
        $renewedBottomCount = 0;
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $players = $this->makeAiSquadOfFiveExpiring();
            $top = $players->first();
            $bottom = $players->last();

            $this->processor->process($this->game, $this->transitionData());

            if ($top->refresh()->contract_until?->toDateString() === self::RENEWED_CONTRACT) {
                $renewedTopCount++;
            }
            if ($bottom->refresh()->contract_until?->toDateString() === self::RENEWED_CONTRACT) {
                $renewedBottomCount++;
            }

            GamePlayer::where('game_id', $this->game->id)->delete();
        }

        // Top bucket target = 80%, bottom bucket target = 15%. Wide bounds to
        // absorb statistical noise — these are smoke tests, not exact rates.
        $this->assertGreaterThanOrEqual(
            (int) ($iterations * 0.65),
            $renewedTopCount,
            "Top-importance player should be renewed in the strong majority of runs (got {$renewedTopCount}/{$iterations})",
        );
        $this->assertLessThanOrEqual(
            (int) ($iterations * 0.30),
            $renewedBottomCount,
            "Bottom-importance player should rarely be renewed (got {$renewedBottomCount}/{$iterations})",
        );
        $this->assertGreaterThan(
            $renewedBottomCount,
            $renewedTopCount,
            'Top-importance retention must outpace bottom-importance retention',
        );
    }

    public function test_does_not_touch_user_team_players(): void
    {
        $userPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->userTeam)
            ->create([
                'date_of_birth' => '1998-01-01',
                'overall_score' => 80,
                'contract_until' => self::EXPIRING_CONTRACT,
            ]);

        $this->processor->process($this->game, $this->transitionData());

        $this->assertSame(
            self::EXPIRING_CONTRACT,
            $userPlayer->refresh()->contract_until->toDateString(),
            'User-team contracts must never be auto-extended by the AI processor',
        );
    }

    public function test_does_not_touch_veterans(): void
    {
        // Build a squad so the veteran has computable importance, but a
        // contract that should not be touched regardless of the roll.
        for ($i = 0; $i < 4; $i++) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($this->aiTeam)
                ->create([
                    'date_of_birth' => '1998-01-01',
                    'overall_score' => 70,
                    'contract_until' => '2029-06-30',
                ]);
        }

        $veteranBirth = Carbon::parse($this->game->current_date)->subYears(36)->toDateString();
        $veteran = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->aiTeam)
            ->create([
                'date_of_birth' => $veteranBirth,
                'overall_score' => 90,
                'contract_until' => self::EXPIRING_CONTRACT,
            ]);

        // Run many times — veteran must NEVER flip to the renewed date.
        for ($i = 0; $i < 50; $i++) {
            $this->processor->process($this->game, $this->transitionData());
            $this->assertSame(
                self::EXPIRING_CONTRACT,
                $veteran->refresh()->contract_until->toDateString(),
                'Veterans must not be touched (they keep the season-end coin flip)',
            );
        }
    }

    public function test_does_not_touch_loaned_out_players(): void
    {
        $loanedPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->aiTeam)
            ->create([
                'date_of_birth' => '1998-01-01',
                'overall_score' => 80,
                'contract_until' => self::EXPIRING_CONTRACT,
            ]);

        $borrowingTeam = Team::factory()->create();
        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $loanedPlayer->id,
            'parent_team_id' => $this->aiTeam->id,
            'loan_team_id' => $borrowingTeam->id,
            'started_at' => '2026-08-01',
            'return_at' => '2027-06-30',
            'status' => Loan::STATUS_ACTIVE,
        ]);

        for ($i = 0; $i < 50; $i++) {
            $this->processor->process($this->game, $this->transitionData());
            $this->assertSame(
                self::EXPIRING_CONTRACT,
                $loanedPlayer->refresh()->contract_until->toDateString(),
                'On-loan players must not have their contracts extended here — renewal authority sits with the parent club flow',
            );
        }
    }

    public function test_records_metadata_with_considered_and_renewed_counts(): void
    {
        $this->makeAiSquadOfFiveExpiring();

        $data = $this->processor->process($this->game, $this->transitionData());

        $meta = $data->getMetadata('aiPreemptiveRenewals');
        $this->assertIsArray($meta);
        $this->assertSame(5, $meta['considered']);
        $this->assertGreaterThanOrEqual(0, $meta['renewed']);
        $this->assertLessThanOrEqual(5, $meta['renewed']);
    }

    /**
     * Five-player AI squad sorted high-to-low by overall_score so the first
     * lands in the top importance bucket and the last in the bottom bucket.
     */
    private function makeAiSquadOfFiveExpiring(): \Illuminate\Support\Collection
    {
        $scores = [90, 80, 70, 60, 50];
        $players = collect();
        foreach ($scores as $score) {
            $players->push(
                GamePlayer::factory()
                    ->forGame($this->game)
                    ->forTeam($this->aiTeam)
                    ->create([
                        'date_of_birth' => '1998-01-01',
                        'overall_score' => $score,
                        'contract_until' => self::EXPIRING_CONTRACT,
                    ]),
            );
        }
        return $players;
    }

    private function transitionData(): SeasonTransitionData
    {
        return new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->game->competition_id ?? 'ESP1',
        );
    }
}
