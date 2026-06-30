<?php

namespace App\Support;

/**
 * Presentation style for a pre-match narrative category — an icon key, badge
 * colours, and an optional deep link. Lets the dashboard "News" card render each
 * MatchNarrative as an icon-tagged story item that shares the inbox's visual
 * vocabulary.
 *
 * Colour classes are the literal strings already used by
 * GameNotification::getTypeClasses() so they're guaranteed in the Tailwind build
 * (and so News + Inbox use one palette). Routes are gameId-only, so a linked
 * story can never resolve to a broken URL.
 */
class NarrativePresenter
{
    /**
     * @return array{icon: string, bg: string, text: string, route: ?string}
     */
    public static function style(string $category): array
    {
        return match ($category) {
            'market' => ['icon' => 'market', 'bg' => 'bg-cyan-500/10', 'text' => 'text-cyan-500', 'route' => 'game.transfers'],
            'scouting' => ['icon' => 'scouting', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-500', 'route' => 'game.opponent-analysis'],
            'rivalry' => ['icon' => 'rivalry', 'bg' => 'bg-orange-500/10', 'text' => 'text-orange-500', 'route' => 'game.opponent-analysis'],
            'european' => ['icon' => 'european', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-500', 'route' => null],
            'cup' => ['icon' => 'cup', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-500', 'route' => null],
            'stakes' => ['icon' => 'stakes', 'bg' => 'bg-yellow-500/10', 'text' => 'text-yellow-500', 'route' => null],
            'form' => ['icon' => 'form', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-500', 'route' => null],
            'mood' => ['icon' => 'mood', 'bg' => 'bg-violet-500/10', 'text' => 'text-violet-500', 'route' => null],
            default => ['icon' => 'news', 'bg' => 'bg-slate-500/10', 'text' => 'text-slate-400', 'route' => null],
        };
    }
}
