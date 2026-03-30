<?php

namespace App\Modules\Competition\DTOs;

enum QualificationStatus: string
{
    case Advanced = 'advanced';
    case Playoff = 'playoff';
    case Eliminated = 'eliminated';
}
