@props(['alerts', 'game'])

{{-- Blocking, must-dismiss popup for the highest-stakes notifications
     (PRIORITY_CRITICAL — see GameNotification). Auto-opens on page load via
     <x-modal :show>. The "Got it" button posts the acknowledge action, which
     marks these alerts read so they don't pop again. Closing via backdrop/escape
     only hides it for this page view, so an unacknowledged alert returns on the
     next page — by design, so a critical event can't be silently missed. --}}
@if($alerts->isNotEmpty())
<div x-data>
    <x-modal name="critical-alert" :show="true" maxWidth="md">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-border-default">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-red-600/15 shrink-0">
                <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
            </span>
            <h3 class="font-heading text-lg font-semibold text-text-primary">{{ __('notifications.alert_heading') }}</h3>
        </div>

        <div class="max-h-[60vh] overflow-y-auto divide-y divide-border-default">
            @foreach($alerts as $alert)
                @php $classes = $alert->getTypeClasses(); @endphp
                <div class="flex items-start gap-3 px-5 py-4">
                    <x-notification-icon :icon="$alert->icon" :icon-bg="$classes['icon_bg']" :icon-text="$classes['icon_text']" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-text-primary">{{ $alert->title }}</p>
                        @if($alert->message)
                        <p class="text-xs text-text-muted mt-1 leading-relaxed">{{ $alert->message }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-end px-5 py-4 border-t border-border-default">
            <form action="{{ route('game.notifications.acknowledge-critical', $game->id) }}" method="POST">
                @csrf
                <x-primary-button type="submit" color="red">{{ __('notifications.alert_dismiss') }}</x-primary-button>
            </form>
        </div>
    </x-modal>
</div>
@endif
