<?php

namespace App\Modules\LiveMatch\Enums;

enum LiveMatchSide: string
{
    case Home = 'home';
    case Away = 'away';

    public function opposite(): self
    {
        return $this === self::Home ? self::Away : self::Home;
    }
}
