@props(['alert', 'game'])

{{-- Blocking, must-dismiss popup for the highest-stakes notifications
     (PRIORITY_CRITICAL — see GameNotification). Shows one alert at a time (the
     most recent unread critical) and auto-opens on page load via <x-modal :show>.

     The primary button is contextual to the alert type ("Review offer", "View
     competition", …): it posts game.notifications.read, which marks this alert
     read and redirects to the relevant page (reusing MarkNotificationRead). The
     quieter "Dismiss" marks this alert read without navigating. Either way, any
     other pending critical surfaces on the next load. Closing via backdrop/escape
     only hides it for this page view, so an unacknowledged alert returns on the
     next page — by design, so a critical event can't be silently missed. --}}
@if($alert)
<div x-data>
    <x-modal name="critical-alert" :show="true" maxWidth="md">
        {{-- Header banner (no close button: must-act). The red icon + "important
             alert" eyebrow carry the severity, divided from the notification below
             so the alert content reads as the focal point. --}}
        <x-modal-header tone="danger" eyebrow>
            <x-slot:icon>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
            </x-slot:icon>
            {{ __('notifications.alert_heading') }}
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
                <x-secondary-button type="submit">{{ __('notifications.alert_dismiss') }}</x-secondary-button>
            </form>
            {{-- Contextual action: marks this alert read and jumps to its page. --}}
            <form action="{{ route('game.notifications.read', [$game->id, $alert->id]) }}" method="POST">
                @csrf
                <x-primary-button type="submit">{{ $alert->getActionLabel() }}</x-primary-button>
            </form>
        </div>
    </x-modal>
</div>
@endif
