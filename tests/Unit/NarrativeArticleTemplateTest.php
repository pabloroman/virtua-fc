<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Team;
use Tests\TestCase;

/**
 * Guard against Spanish article bugs in the pre-match narrative templates.
 *
 * The ES templates no longer hardcode articles; they use the contracted param
 * family built by MatchNarrativeService::teamParams()/competitionParams() from
 * the models' article-aware helpers (`:opponent_a` → "al Atalanta" / "a la Real
 * Sociedad" / "a Osasuna"). This test renders every template through the real
 * translator with those params so it catches:
 *  - a template that prefixes an already-contracted param (e.g. "a :opponent_a"
 *    → "a al Atalanta"),
 *  - the sentence-initial capitalization trick (`:Opponent_el` → "El Atalanta"),
 *  - feminine and article-less clubs, and national teams (no article).
 */
class NarrativeArticleTemplateTest extends TestCase
{
    private function team(string $name, string $type = 'club'): Team
    {
        $team = new Team();
        $team->name = $name;
        $team->type = $type;

        return $team;
    }

    private function competition(string $id): Competition
    {
        $competition = new Competition();
        $competition->id = $id;

        return $competition;
    }

    /** The param bag MatchNarrativeService builds for a team under a given prefix. */
    private function teamParams(string $prefix, Team $team): array
    {
        return [
            $prefix => $team->name,
            "{$prefix}_el" => $team->nameWithEl(),
            "{$prefix}_a" => $team->nameWithA(),
            "{$prefix}_de" => $team->nameWithDe(),
        ];
    }

    private function competitionParams(string $prefix, Competition $comp): array
    {
        return [
            $prefix => $comp->shortName(),
            "{$prefix}_el" => $comp->nameWithEl(),
            "{$prefix}_a" => $comp->nameWithA(),
            "{$prefix}_de" => $comp->nameWithDe(),
        ];
    }

    /** A full bag so no placeholder is ever left unreplaced during the sweep. */
    private function bag(Team $opponent, Competition $comp): array
    {
        return array_merge(
            $this->teamParams('opponent', $opponent),
            $this->teamParams('club', $opponent),
            $this->competitionParams('competition', $comp),
            [
                'player' => 'Jugador',
                'score' => '2-1',
                'points' => 3,
                'wins' => 1,
                'total' => 5,
                'position' => '3º',
                'diff' => 3,
                'group' => 'A',
                'count' => 2,
                'round' => 'Cuartos de final',
            ],
        );
    }

    public function test_narrative_templates_do_not_duplicate_articles(): void
    {
        $samples = [
            $this->team('Atalanta BC'),          // masculine → el / al / del
            $this->team('Real Sociedad'),        // feminine  → la / a la / de la
            $this->team('CA Osasuna'),           // no article → "" / a / de
            $this->team('España', 'national'),   // national  → no article
        ];
        $competitions = [$this->competition('UCL'), $this->competition('WC2026')];

        $forbidden = [
            ' a al ', ' a a la ', ' de del ', ' de de la ',
            ' el el ', ' la la ', ' al al ', ' del del ',
            ' a la a la ', ' de la de la ',
        ];

        $templates = trans('narrative', [], 'es');
        $this->assertIsArray($templates);

        foreach ($templates as $key => $template) {
            foreach ($samples as $opponent) {
                foreach ($competitions as $comp) {
                    $rendered = trans("narrative.{$key}", $this->bag($opponent, $comp), 'es');
                    $padded = " {$rendered} ";

                    foreach ($forbidden as $bad) {
                        $this->assertStringNotContainsString(
                            $bad,
                            $padded,
                            "narrative.{$key} renders a duplicated article '{$bad}' for {$opponent->name} / {$comp->id}: \"{$rendered}\""
                        );
                    }
                }
            }
        }
    }

    public function test_opponent_contractions_render_correctly(): void
    {
        $cases = [
            'Atalanta BC'   => 'Toca viajar para medirte al Atalanta BC.',
            'Real Sociedad' => 'Toca viajar para medirte a la Real Sociedad.',
            'CA Osasuna'    => 'Toca viajar para medirte a CA Osasuna.',
        ];

        foreach ($cases as $name => $expected) {
            $rendered = trans('narrative.opponent_preview_away_v2', $this->teamParams('opponent', $this->team($name)), 'es');
            $this->assertSame($expected, $rendered);
        }
    }

    public function test_sentence_initial_article_is_capitalized(): void
    {
        $masculine = trans('narrative.direct_rival_v1', $this->teamParams('opponent', $this->team('Atalanta BC')) + ['points' => 3], 'es');
        $this->assertStringStartsWith('El Atalanta BC está', $masculine);

        $feminine = trans('narrative.direct_rival_v1', $this->teamParams('opponent', $this->team('Real Sociedad')) + ['points' => 3], 'es');
        $this->assertStringStartsWith('La Real Sociedad está', $feminine);
    }

    public function test_competition_articles_render_correctly(): void
    {
        // The European lines now name the opponent + venue (see the "merge
        // European lines" change), so render through a full bag. This still
        // exercises the competition contractions: :competition_el → "la
        // Champions League", :competition_de → "de la Champions League".
        $bag = $this->bag($this->team('Atalanta BC'), $this->competition('UCL'));

        $this->assertSame(
            'Vuelve la Champions League a tu estadio: recibes al Atalanta BC.',
            trans('narrative.euro_group_home_v2', $bag, 'es'),
        );
        $this->assertSame(
            'La final de la Champions League ante el Atalanta BC. Una noche para entrar en la historia.',
            trans('narrative.euro_final_v1', $bag, 'es'),
        );
    }
}
