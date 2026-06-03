@props(['alert', 'game'])

{{-- Blocking, must-dismiss popup for the highest-stakes notifications
     (PRIORITY_CRITICAL — see GameNotification). Shows one alert at a time (the
     most recent unread critical) and auto-opens on page load via <x-modal :show>.

     The header adapts to the alert: positive events (isCelebratory — qualifying
     or winning a cup) render in a green "¡Enhorabuena!" frame with a trophy icon;
     everything else uses the red "important alert" frame. The primary button is
     contextual to the alert type ("Review offer", "View competition", …): it posts
     game.notifications.read, which marks this alert read and redirects to the
     relevant page (reusing MarkNotificationRead). The quieter secondary button
     marks this alert read without navigating. Either way, any other pending
     critical surfaces on the next load. Closing via backdrop/escape only hides it
     for this page view, so an unacknowledged alert returns on the next page — by
     design, so a critical event can't be silently missed. --}}
@if($alert)
@php $celebratory = $alert->isCelebratory(); @endphp
<div x-data>
    <x-modal name="critical-alert" :show="true" maxWidth="md">
        {{-- Header banner (no close button: must-act). The icon + eyebrow carry the
             tone — celebratory (green/trophy) for positive events, danger (red/alert)
             otherwise — divided from the notification below so the alert content
             reads as the focal point. --}}
        <x-modal-header :tone="$celebratory ? 'success' : 'danger'" eyebrow>
            <x-slot:icon>
                @if($celebratory)
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                </svg>
                @else
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                @endif
            </x-slot:icon>
            {{ $celebratory ? __('notifications.celebration_heading') : __('notifications.alert_heading') }}
        </x-modal-header>

        <div class="px-5 py-4">
            <p class="font-heading text-lg font-semibold text-text-primary">{{ $alert->title }}</p>
            @if($alert->message)
            <p class="mt-1.5 text-sm text-text-muted leading-relaxed">{{ $alert->message }}</p>
            @endif
        </div>

        <div class="flex items-center justify-end gap-3 px-5 py-4 border-t border-border-default">
            {{-- Quiet dismiss: marks this alert read without navigating. --}}
            <form action="{{ route('game.notifications.acknowledge-critical', $game->id) }}" method="POST">
                @csrf
                <input type="hidden" name="notification_id" value="{{ $alert->id }}">
                <x-secondary-button size="sm" type="submit">{{ $celebratory ? __('notifications.alert_continue') : __('notifications.alert_dismiss') }}</x-secondary-button>
            </form>
            {{-- Contextual action: marks this alert read and jumps to its page. --}}
            <form action="{{ route('game.notifications.read', [$game->id, $alert->id]) }}" method="POST">
                @csrf
                <x-primary-button size="sm" type="submit">{{ $alert->getActionLabel() }}</x-primary-button>
            </form>
        </div>
    </x-modal>
</div>
@endif
