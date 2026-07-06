<?php

namespace App\Support;

/**
 * Presentation style for a pre-match narrative category — an icon key, badge
 * colours, and an optional deep link. Lets the dashboard "News" card render each
 * MatchNarrative as an icon-tagged story item that shares the inbox's visual
 * vocabulary.
 *
 * The icon key is one understood by the shared <x-notification-icon> component,
 * so News and the inbox draw from a single SVG set. Colour classes are literal
 * strings already used by GameNotification::getTypeClasses(), so they're
 * guaranteed in the Tailwind build (and News + Inbox use one palette). Routes are
 * gameId-only, so a linked story can never resolve to a broken URL.
 */
class NarrativePresenter
{
    /**
     * @return array{icon: string, bg: string, text: string, route: ?string}
     */
    public static function style(string $category): array
    {
        return match ($category) {
            'market' => ['icon' => 'transfer', 'bg' => 'bg-cyan-500/10', 'text' => 'text-cyan-500', 'route' => 'game.transfers'],
            'scouting' => ['icon' => 'scout', 'bg' => 'bg-teal-500/10', 'text' => 'text-teal-500', 'route' => 'game.opponent-analysis'],
            'rivalry' => ['icon' => 'fire', 'bg' => 'bg-orange-500/10', 'text' => 'text-orange-500', 'route' => 'game.opponent-analysis'],
            'european' => ['icon' => 'academy', 'bg' => 'bg-blue-500/10', 'text' => 'text-blue-500', 'route' => null],
            'cup' => ['icon' => 'trophy', 'bg' => 'bg-amber-500/10', 'text' => 'text-amber-500', 'route' => null],
            'stakes' => ['icon' => 'chart-bar', 'bg' => 'bg-yellow-500/10', 'text' => 'text-yellow-500', 'route' => null],
            'form' => ['icon' => 'trending-up', 'bg' => 'bg-emerald-500/10', 'text' => 'text-emerald-500', 'route' => null],
            'mood' => ['icon' => 'injury', 'bg' => 'bg-violet-500/10', 'text' => 'text-violet-500', 'route' => null],
            default => ['icon' => 'megaphone', 'bg' => 'bg-slate-500/10', 'text' => 'text-slate-400', 'route' => null],
        };
    }
}
