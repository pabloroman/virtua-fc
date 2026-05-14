<?php

namespace App\Modules\Stadium\Enums;

enum StadiumProjectStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
