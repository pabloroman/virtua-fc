<?php

namespace Tests\Unit;

use App\Modules\Squad\Services\PlayerNameGenerator;
use PHPUnit\Framework\TestCase;

class PlayerNameGeneratorTest extends TestCase
{
    private PlayerNameGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PlayerNameGenerator();
    }

    public function test_generates_two_word_name_for_known_nationality(): void
    {
        $name = $this->generator->generate('Spain');

        $this->assertNotEmpty($name);
        $this->assertMatchesRegularExpression('/\S+ \S+/', $name);
    }

    public function test_maps_common_nationalities_to_expected_locales(): void
    {
        $this->assertSame('es_ES', $this->generator->localeFor('Spain'));
        $this->assertSame('en_GB', $this->generator->localeFor('England'));
        $this->assertSame('fr_FR', $this->generator->localeFor('France'));
        $this->assertSame('de_DE', $this->generator->localeFor('Germany'));
        $this->assertSame('it_IT', $this->generator->localeFor('Italy'));
        $this->assertSame('pt_BR', $this->generator->localeFor('Brazil'));
        $this->assertSame('ja_JP', $this->generator->localeFor('Japan'));
    }

    public function test_unknown_nationality_falls_back_to_en_us(): void
    {
        $this->assertSame('en_US', $this->generator->localeFor('Atlantis'));
        $this->assertNotEmpty($this->generator->generate('Atlantis'));
    }

    public function test_generates_diverse_names_across_many_calls(): void
    {
        // With Faker's Spanish name space (hundreds of first names × thousands
        // of surnames), 200 draws should produce nearly all-unique names.
        // Anything less than ~180 unique out of 200 would indicate a broken
        // locale mapping or a cached/constant Faker seed.
        $names = [];
        for ($i = 0; $i < 200; $i++) {
            $names[] = $this->generator->generate('Spain');
        }

        $unique = array_unique($names);
        $this->assertGreaterThan(180, count($unique));
    }
}
