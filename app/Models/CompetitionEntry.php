<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $game_id
 * @property string $competition_id
 * @property string $team_id
 * @property int $entry_round
 * @property-read \App\Models\Competition $competition
 * @property-read \App\Models\Game $game
 * @property-read \App\Models\Team $team
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry whereCompetitionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry whereEntryRound($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry whereGameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CompetitionEntry whereTeamId($value)
 * @mixin \Eloquent
 */
class CompetitionEntry extends Model
{
    protected $table = 'competition_entries';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'game_id',
        'competition_id',
        'team_id',
        'entry_round',
    ];

    protected $casts = [
        'entry_round' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
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
