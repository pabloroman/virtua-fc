<?php

namespace App\Modules\Match\Support;

use App\Models\ClubProfile;
use App\Models\Team;

/**
 * How good a squad-less cup entrant is.
 *
 * A ghost is a `Team` row with a name, a crest and no players — the way a cup
 * fields a whole pyramid without seeding a roster per club. Its matches are
 * resolved from a stand-in strength, because there is no XI to average.
 *
 * That stand-in used to be one hardcoded number for every ghost in the game, so
 * a regional amateur side and a second-tier club with 30,000 seats were exactly
 * as hard to beat. Reputation separates them: `ClubProfilesSeeder` writes a
 * profile for every team, curated where the club is notable and
 * local-by-default where it is not, so the tier is already in the database and
 * costs no new data.
 *
 * The band is deliberately narrow. A ghost should be beatable by any
 * professional side on nearly every occasion — the point is that "nearly" stops
 * meaning "always".
 */
class GhostStrength
{
    /**
     * Paper strength for a squad-less side, in the same 0..1 band a real XI
     * averages into.
     */
    public static function forTeam(?Team $team): float
    {
        return self::forReputation($team?->clubProfile?->reputation_level);
    }

    public static function forReputation(?string $reputationLevel): float
    {
        $band = config('match_simulation.ghost_strength', []);
        $default = (float) ($band['default'] ?? 0.30);

        if ($reputationLevel === null) {
            return $default;
        }

        return (float) ($band[$reputationLevel] ?? $default);
    }

    /**
     * The floor a thin-but-not-empty lineup falls back to. A side with four
     * players is not a ghost — it is a real club that could not field a team —
     * so it keeps the flat amateur rating rather than borrowing a reputation
     * it has not earned on the pitch.
     */
    public static function thinLineup(): float
    {
        return (float) (config('match_simulation.ghost_strength.default') ?? 0.30);
    }

    /**
     * Reputation levels in the order they are ranked, so a caller can reason
     * about the band without reaching into config.
     *
     * @return array<int, string>
     */
    public static function levels(): array
    {
        return ClubProfile::REPUTATION_TIERS;
    }
}
