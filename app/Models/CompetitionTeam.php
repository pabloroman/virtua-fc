<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CompetitionTeam extends Pivot
{
    protected $table = 'competition_teams';

    public $timestamps = false;
}
