@props(['notification', 'game'])

@php
    $classes = $notification->getTypeClasses();
    $badge = $notification->getPriorityBadge();
@endphp

<a href="{{ route($notification->getNavigationRoute(), $notification->getNavigationParams($game->id)) }}" class="block px-4 py-3 hover:bg-surface-700/30 transition-colors">
    <div class="flex items-start gap-3">
        <x-notification-icon :icon="$notification->icon" :icon-bg="$classes['icon_bg']" :icon-text="$classes['icon_text']" />

        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-text-primary">{{ $notification->title }}</p>
            @if($notification->message)
            <p class="text-xs text-text-muted mt-0.5 leading-relaxed">{{ $notification->message }}</p>
            @endif
            <div class="flex items-center gap-2 mt-1">
                @if($notification->game_date)
                <span class="text-[10px] text-text-muted">{{ $notification->game_date->format('j M') }}</span>
                @endif
                @if($badge)
                <span class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full {{ $badge['bg'] }} {{ $badge['text'] }}">
                    {{ $badge['label'] }}
                </span>
                @endif
            </div>
        </div>
    </div>
</a>
