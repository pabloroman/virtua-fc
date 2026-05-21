<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePlayerTemplateTournamentInfo extends Model
{
    use HasUuids;

    protected $table = 'game_player_template_tournament_info';

    protected $connection = 'pgsql_control';

    public $timestamps = false;

    protected $fillable = [
        'game_player_template_id',
        'is_injured',
        'is_called_up',
        'club_name',
        'club_crest_url',
    ];

    protected $casts = [
        'is_injured' => 'boolean',
        'is_called_up' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(GamePlayerTemplate::class, 'game_player_template_id');
    }
}
