@props(['notification', 'game'])

@php
    $classes = $notification->getTypeClasses();
    $badge = $notification->getPriorityBadge();
    $isUnread = !$notification->isRead();
@endphp

<form action="{{ route('game.notifications.read', [$game->id, $notification->id]) }}" method="POST">
    @csrf
    <button type="submit" class="w-full text-left px-4 py-3 hover:bg-surface-700/30 transition-colors {{ $isUnread ? '' : 'opacity-50' }}">
        <div class="flex items-start gap-3">
            <x-notification-icon :icon="$notification->icon" :icon-bg="$classes['icon_bg']" :icon-text="$classes['icon_text']" />

            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-text-primary">{{ $notification->title }}</p>
                @if($notification->message)
                <p class="text-[11px] text-text-muted mt-0.5 leading-relaxed">{{ $notification->message }}</p>
                @endif
                <div class="flex items-center gap-2 mt-1">
                    @if($notification->game_date)
                    <span class="text-[9px] text-text-faint">{{ $notification->game_date->format('j M') }}</span>
                    @endif
                    @if($badge)
                    <span class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide rounded-full {{ $badge['bg'] }} {{ $badge['text'] }}">
                        {{ $badge['label'] }}
                    </span>
                    @endif
                </div>
            </div>

            @if($isUnread)
            <div class="w-2 h-2 rounded-full {{ $classes['dot'] }} shrink-0 mt-1.5"></div>
            @endif
        </div>
    </button>
</form>
