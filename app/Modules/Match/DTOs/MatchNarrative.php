<?php

namespace App\Modules\Match\DTOs;

readonly class MatchNarrative
{
    public function __construct(
        public string $text,
        public string $category,
    ) {}
}
