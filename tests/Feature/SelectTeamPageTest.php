<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The new-game page had no coverage, so a Blade or view-model mistake reached
 * production as a 500. These pin the league picker's contract: the options it
 * offers, and the fact that a Primera Federación group is offered once rather
 * than twice.
 */
class SelectTeamPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // $countries is cached for an hour under a fixed key, which would leak
        // one test's competitions into the next.
        Cache::flush();
    }

    public function test_it_renders_with_the_league_picker(): void
    {
        $this->seedLeague('ESP1', 'ES');

        $this->actingAs($this->user())
            ->get(route('select-team'))
            ->assertOk()
            ->assertSee('league-select', escape: false)
            ->assertSee('ESP1', escape: false);
    }

    public function test_the_two_primera_federacion_groups_share_one_option(): void
    {
        $this->seedLeague('ESP1', 'ES');
        $this->seedLeague('ESP3A', 'ES');
        $this->seedLeague('ESP3B', 'ES');

        $response = $this->actingAs($this->user())->get(route('select-team'));

        $response->assertOk();

        $leagues = $this->pickerOptions($response->getContent());
        $values = array_column($leagues, 'value');

        $this->assertContains('ESP3', $values, 'the combined Primera Federación entry should be offered');
        $this->assertNotContains('ESP3A', $values);
        $this->assertNotContains('ESP3B', $values);
        $this->assertSame(array_unique($values), $values, 'no league should be offered twice');
    }

    public function test_the_default_selection_is_an_option_the_picker_offers(): void
    {
        // The default used to be derived from a separate list, so it could name
        // a league the picker had folded away.
        $this->seedLeague('ESP3A', 'ES');
        $this->seedLeague('ESP3B', 'ES');

        $response = $this->actingAs($this->user())->get(route('select-team'));

        $response->assertOk();

        $values = array_column($this->pickerOptions($response->getContent()), 'value');
        $this->assertContains($this->defaultSelection($response->getContent()), $values);
    }

    /**
     * Pull the options back out of the page. `@js()` renders them as
     * `JSON.parse('...')` with the quotes unicode-escaped, so the captured
     * literal is decoded once as a JS string and then as JSON.
     *
     * @return array<int, array{value: string, label: string, flag: string}>
     */
    private function pickerOptions(string $html): array
    {
        preg_match("/leagues: JSON\\.parse\\('(.*?)'\\)/", $html, $m);
        $this->assertNotEmpty($m, 'the page should hand the picker a leagues array');

        return json_decode((string) json_decode('"' . $m[1] . '"'), true) ?? [];
    }

    private function defaultSelection(string $html): string
    {
        preg_match("/openTab: '(\\w+)'/", $html, $m);
        $this->assertNotEmpty($m, 'the page should choose a default league');

        return $m[1];
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    private function seedLeague(string $id, string $country): void
    {
        $competition = Competition::factory()->league()->create([
            'id' => $id,
            'country' => $country,
            'flag' => strtolower($country),
        ]);

        Team::factory()->count(2)->create(['country' => $country])
            ->each(fn (Team $team) => $competition->teams()->attach($team, ['season' => config('season.current')]));
    }
}
