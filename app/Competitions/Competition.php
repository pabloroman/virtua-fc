<?php

namespace App\Competitions;

interface Competition
{
    public function getName(): string;
    public function getCode(): string;
    public function getCountryAlpha2(): string;
    public function getInitialTeams(): array;
}
