<?php

namespace App\Modules\Match\DTOs;

use App\Models\GameMatch;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;

readonly class TacticalConfig
{
    public function __construct(
        public Formation $homeFormation,
        public Formation $awayFormation,
        public Mentality $homeMentality,
        public Mentality $awayMentality,
        public PlayingStyle $homePlayingStyle,
        public PlayingStyle $awayPlayingStyle,
        public PressingIntensity $homePressing,
        public PressingIntensity $awayPressing,
        public DefensiveLineHeight $homeDefLine,
        public DefensiveLineHeight $awayDefLine,
    ) {}

    public static function fromMatch(GameMatch $match): self
    {
        return new self(
            homeFormation: Formation::tryFrom($match->home_formation) ?? Formation::F_4_3_3,
            awayFormation: Formation::tryFrom($match->away_formation) ?? Formation::F_4_3_3,
            homeMentality: Mentality::tryFrom($match->home_mentality ?? '') ?? Mentality::BALANCED,
            awayMentality: Mentality::tryFrom($match->away_mentality ?? '') ?? Mentality::BALANCED,
            homePlayingStyle: PlayingStyle::tryFrom($match->home_playing_style ?? '') ?? PlayingStyle::BALANCED,
            awayPlayingStyle: PlayingStyle::tryFrom($match->away_playing_style ?? '') ?? PlayingStyle::BALANCED,
            homePressing: PressingIntensity::tryFrom($match->home_pressing ?? '') ?? PressingIntensity::STANDARD,
            awayPressing: PressingIntensity::tryFrom($match->away_pressing ?? '') ?? PressingIntensity::STANDARD,
            homeDefLine: DefensiveLineHeight::tryFrom($match->home_defensive_line ?? '') ?? DefensiveLineHeight::NORMAL,
            awayDefLine: DefensiveLineHeight::tryFrom($match->away_defensive_line ?? '') ?? DefensiveLineHeight::NORMAL,
        );
    }

    public function neutralized(): self
    {
        return new self(
            homeFormation: $this->homeFormation,
            awayFormation: $this->awayFormation,
            homeMentality: $this->homeMentality,
            awayMentality: $this->awayMentality,
            homePlayingStyle: PlayingStyle::BALANCED,
            awayPlayingStyle: PlayingStyle::BALANCED,
            homePressing: PressingIntensity::STANDARD,
            awayPressing: PressingIntensity::STANDARD,
            homeDefLine: DefensiveLineHeight::NORMAL,
            awayDefLine: DefensiveLineHeight::NORMAL,
        );
    }
}
