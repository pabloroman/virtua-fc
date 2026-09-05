<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $competition_id
 * @property string $team_id
 * @property string $season
 * @property int $entry_round
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam whereEntryRound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam whereSeason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionTeam whereTeamId($value)
 * @mixin \Eloquent
 */
class CompetitionTeam extends Pivot
{
    /**
     * Restrict `competition_teams` rows to the season their competition is
     * currently on.
     *
     * The table is keyed (competition_id, team_id, season) and the seeder only
     * ever updateOrInserts, so re-seeding a new base season onto a live database
     * leaves the previous season's rows in place — deliberately, since games
     * still on the old season need them. Every read therefore has to say which
     * season it means, or it silently gets the union of all of them and, for
     * example, offers a relegated club in the new-game picker.
     *
     * Expressed as a correlated subquery rather than a bound value on purpose:
     *
     *  - `$this->season` is unavailable where it matters. Eloquent builds a
     *    relation from a *blank* model for `with()` and `whereHas()`, so a
     *    `wherePivot('season', $this->season)` would compare against null there
     *    and match nothing at all.
     *  - `config('season.current')` is wrong for WC2026, which is seeded at
     *    season '2025' (SeedWorldCupData::SEASON) while GAME_SEASON is '2026'.
     *
     * Comparing each row against its own competition is correct in every query
     * shape and for every competition, and holds even if one competition is
     * re-seeded on its own via `app:seed-reference-data --country=`.
     */
    public const SEASON_MATCHES_COMPETITION = 'competition_teams.season = (select season from competitions where competitions.id = competition_teams.competition_id)';

    protected $table = 'competition_teams';

    public $timestamps = false;

    public function scopeForCurrentSeason(Builder $query): void
    {
        $query->whereRaw(self::SEASON_MATCHES_COMPETITION);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
