<?php

namespace App\Models;

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
    protected $table = 'competition_teams';

    public $timestamps = false;

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
