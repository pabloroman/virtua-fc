<?php

namespace Tests\Feature;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\Game;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\User;
use App\Modules\Manager\Services\JobOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the Pro Manager loyalty bug: JobOfferService used
 * to read club reputation from ClubProfile (control-plane, static), which
 * meant a manager who grew their original team into a giant never saw their
 * club's growth reflected in the offer ladder. Every other system reads from
 * the per-game TeamReputation, so JobOfferService now does too.
 *
 * The tests exercise generateEndOfSeasonOffers() directly so the grade →
 * rank-shift plan and the candidate-pool filter are both validated without
 * depending on standings resolution.
 */
class JobOfferServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Loyalty regression: a manager whose ClubProfile is still `local`
     * (because the club was originally a Primera RFEF minnow) but whose
     * in-game TeamReputation has climbed to `elite` after years of league
     * wins must field tier-1 elite offers on an exceptional season — i.e.,
     * the Real Madrid-tier offers that the static ClubProfile read used to
     * make unreachable.
     */
    public function test_loyal_manager_with_in_game_elite_reputation_sees_top_tier_offers(): void
    {
        [$game] = $this->seedScenario(
            userClubProfileReputation: ClubProfile::REPUTATION_LOCAL,
            userTeamReputation: ClubProfile::REPUTATION_ELITE,
        );

        // Three rival tier-1 clubs whose in-game TeamReputation is elite —
        // the pool the loyal manager should now be drawing offers from.
        $this->seedRival($game, ClubProfile::REPUTATION_ELITE, ClubProfile::REPUTATION_ELITE);
        $this->seedRival($game, ClubProfile::REPUTATION_ELITE, ClubProfile::REPUTATION_ELITE);
        $this->seedRival($game, ClubProfile::REPUTATION_ELITE, ClubProfile::REPUTATION_ELITE);

        $offers = app(JobOfferService::class)
            ->generateEndOfSeasonOffers($game->refresh(), 'exceptional');

        $this->assertNotEmpty($offers, 'An exceptional season at an elite-rep club should produce at least one offer.');

        foreach ($offers as $offer) {
            $this->assertSame(
                ClubProfile::REPUTATION_ELITE,
                $offer->target_reputation_level,
                'Offers for a loyal elite-rep manager should land at tier-1 elite, not the stale ClubProfile tier.',
            );
            $this->assertSame(
                ClubProfile::REPUTATION_ELITE,
                $offer->source_reputation_level,
                'source_reputation_level must reflect the in-game TeamReputation, not the editorial ClubProfile seed.',
            );
        }
    }

    /**
     * Symmetric check on the rival side: a rival club whose ClubProfile is
     * `continental` but whose in-game TeamReputation has slipped to
     * `established` belongs in the established candidate pool, not the
     * continental one. Before the fix the static ClubProfile decided the
     * bucket; now TeamReputation does.
     */
    public function test_rival_in_game_decline_moves_them_into_the_lower_candidate_pool(): void
    {
        [$game] = $this->seedScenario(
            userClubProfileReputation: ClubProfile::REPUTATION_ESTABLISHED,
            userTeamReputation: ClubProfile::REPUTATION_ESTABLISHED,
        );

        // The "declining" rival: editorial seed says continental, in-game
        // truth says established. After the fix this team should be eligible
        // at the established tier-1 rank, not the continental one.
        $declinedRival = $this->seedRival(
            $game,
            clubProfileReputation: ClubProfile::REPUTATION_CONTINENTAL,
            teamReputation: ClubProfile::REPUTATION_ESTABLISHED,
        );

        // "exceeded" deterministically generates two offers: one at +1 rank
        // (tier-1 continental) and one at +0 rank (tier-1 established). With
        // the declined rival as the only candidate at the established slot,
        // it must appear in the result if the per-game filter is correct.
        $offers = app(JobOfferService::class)
            ->generateEndOfSeasonOffers($game->refresh(), 'exceeded');

        $this->assertTrue(
            $offers->contains(fn (ManagerJobOffer $offer) => $offer->team_id === $declinedRival->id),
            'A rival whose in-game TeamReputation matches the user\'s tier should appear in the lateral candidate pool, '
            . 'even when their editorial ClubProfile is one tier higher.',
        );

        foreach ($offers as $offer) {
            if ($offer->team_id === $declinedRival->id) {
                $this->assertSame(
                    ClubProfile::REPUTATION_ESTABLISHED,
                    $offer->target_reputation_level,
                    'The offer snapshot must record the rival\'s in-game tier, not the ClubProfile tier.',
                );
            }
        }
    }

    /**
     * @return array{0: Game, 1: Team}
     */
    private function seedScenario(string $userClubProfileReputation, string $userTeamReputation): array
    {
        $season = '2025';

        $laLiga = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'country' => 'ES',
            'tier' => 1,
            'season' => $season,
        ]);

        $user = User::factory()->create();
        $userTeam = Team::factory()->create(['name' => 'User Team']);

        ClubProfile::create([
            'team_id' => $userTeam->id,
            'reputation_level' => $userClubProfileReputation,
        ]);

        $userTeam->competitions()->attach($laLiga->id, ['season' => $season]);

        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $userTeam->id,
            'competition_id' => $laLiga->id,
            'season' => $season,
            'game_mode' => Game::MODE_CAREER_PRO,
            'current_date' => '2026-05-23',
        ]);

        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $userTeam->id,
            'reputation_level' => $userTeamReputation,
            'base_reputation_level' => $userClubProfileReputation,
            'reputation_points' => TeamReputation::pointsForTier($userTeamReputation),
        ]);
        // resolveLevel memoizes per-process; clear so each test sees its own seed.
        TeamReputation::flushCacheFor($game->id, $userTeam->id);

        return [$game, $userTeam];
    }

    private function seedRival(Game $game, string $clubProfileReputation, string $teamReputation): Team
    {
        $team = Team::factory()->create();

        ClubProfile::create([
            'team_id' => $team->id,
            'reputation_level' => $clubProfileReputation,
        ]);

        $team->competitions()->attach('ESP1', ['season' => $game->season]);

        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => $teamReputation,
            'base_reputation_level' => $clubProfileReputation,
            'reputation_points' => TeamReputation::pointsForTier($teamReputation),
        ]);
        TeamReputation::flushCacheFor($game->id, $team->id);

        return $team;
    }
}
