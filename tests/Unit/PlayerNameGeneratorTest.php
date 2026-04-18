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

    public function test_basque_region_draws_from_basque_pools(): void
    {
        $this->assertRegionUsesPools(
            region: 'basque',
            providerClass: \App\Support\Faker\Provider\eu_ES\Person::class,
        );
    }

    public function test_catalan_region_draws_from_catalan_pools(): void
    {
        $this->assertRegionUsesPools(
            region: 'catalan',
            providerClass: \App\Support\Faker\Provider\ca_ES\Person::class,
        );
    }

    public function test_unknown_region_falls_back_to_nationality_locale(): void
    {
        // A bogus region code should not break generation — the nationality
        // mapping takes over instead.
        $name = $this->generator->generate('Spain', 'atlantean');
        $this->assertNotEmpty($name);
        $this->assertMatchesRegularExpression('/\S+ \S+/', $name);
    }

    /**
     * Pull the protected static first/last name arrays from a Person provider
     * class and assert that 50 generated names fall entirely within those pools.
     * This is how we verify the region override routes to the right provider
     * without coupling to specific random names.
     */
    private function assertRegionUsesPools(string $region, string $providerClass): void
    {
        $firstNames = $this->poolFor($providerClass, 'firstNameMale');
        $lastNames = $this->poolFor($providerClass, 'lastName');

        for ($i = 0; $i < 50; $i++) {
            $name = $this->generator->generate('Spain', $region);
            [$first, $last] = explode(' ', $name, 2);

            $this->assertContains($first, $firstNames, "First name '{$first}' not in {$region} pool");
            $this->assertContains($last, $lastNames, "Surname '{$last}' not in {$region} pool");
        }
    }

    /**
     * @return string[]
     */
    private function poolFor(string $providerClass, string $property): array
    {
        $prop = new \ReflectionProperty($providerClass, $property);
        $prop->setAccessible(true);

        return $prop->getValue();
    }
}
