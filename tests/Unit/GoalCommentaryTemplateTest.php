<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Guard against grammatical bugs in goal-narrative templates.
 *
 * The Spanish templates use placeholders like `:del_team` and `:el_team`
 * that already include the article ("del CD Arenteiro", "de la Real
 * Sociedad", "de Osasuna", "el Real Madrid"). A template that prefixes
 * those placeholders with a literal "de " or "el " produces duplicated
 * prepositions like "de del CD Arenteiro" — which is exactly what the
 * `goal_possession` template did before this test was added.
 */
class GoalCommentaryTemplateTest extends TestCase
{
    public function test_goal_templates_do_not_duplicate_articles(): void
    {
        $sampleTeams = [
            ['name' => 'CD Arenteiro',     'del' => 'del CD Arenteiro',     'el' => 'el CD Arenteiro',     'al' => 'al CD Arenteiro'],
            ['name' => 'Real Sociedad',    'del' => 'de la Real Sociedad',  'el' => 'la Real Sociedad',    'al' => 'a la Real Sociedad'],
            ['name' => 'Osasuna',          'del' => 'de Osasuna',           'el' => 'Osasuna',             'al' => 'a Osasuna'],
        ];

        $groups = [
            'commentary.goal_possession',
            'commentary.goal_counter_attack',
            'commentary.goal_direct',
            'commentary.goal_penalty',
        ];

        $forbiddenSubstrings = [
            ' de del ', ' de de la ', ' de de ',
            ' el el ', ' la la ', ' al al ', ' a la a la ',
        ];

        foreach ($groups as $key) {
            $templates = trans($key, [], 'es');
            $this->assertIsArray($templates, "{$key} should resolve to an array of templates");

            foreach ($templates as $template) {
                foreach ($sampleTeams as $team) {
                    $rendered = strtr($template, [
                        ':player'    => 'Player Name',
                        ':team'      => $team['name'],
                        ':del_team'  => $team['del'],
                        ':el_team'   => $team['el'],
                        ':al_team'   => $team['al'],
                    ]);
                    $padded = " {$rendered} ";

                    foreach ($forbiddenSubstrings as $bad) {
                        $this->assertStringNotContainsString(
                            $bad,
                            $padded,
                            "Template '{$template}' renders with forbidden substring '{$bad}' for team {$team['name']}: \"{$rendered}\""
                        );
                    }
                }
            }
        }
    }
}
