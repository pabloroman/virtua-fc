<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $primaryKey = 'uuid';
    public $incrementing = false;

    public $guarded = [];
}
