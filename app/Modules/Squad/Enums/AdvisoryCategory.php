<?php

namespace App\Modules\Squad\Enums;

/**
 * Topic of a Squad Planner advisory. Lets the UI group or filter without
 * parsing the message string (e.g. "show me only wage-cliff issues").
 */
enum AdvisoryCategory: string
{
    case DEPTH = 'depth';
    case QUALITY = 'quality';
    case BACKUP = 'backup';
    case OVERLOAD = 'overload';
    case AGE = 'age';
    case WAGE = 'wage';
    case DEVELOPMENT = 'development';
    case DEPARTURE = 'departure';
}
