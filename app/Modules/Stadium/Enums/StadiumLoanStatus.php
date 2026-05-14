<?php

namespace App\Modules\Stadium\Enums;

enum StadiumLoanStatus: string
{
    case Active = 'active';
    case Repaid = 'repaid';
}
