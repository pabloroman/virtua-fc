<?php

namespace App\Modules\LiveMatch\Enums;

enum QueuedActionType: string
{
    case Substitution = 'sub';
    case Formation = 'formation';
    case Mentality = 'mentality';
}
