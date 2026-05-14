<?php

namespace App\Modules\Stadium\Enums;

enum StadiumProjectType: string
{
    case Supplementary = 'supplementary';
    case StandExpansion = 'stand_expansion';
    case Rebuild = 'rebuild';
    case UefaUpgrade = 'uefa_upgrade';
}
