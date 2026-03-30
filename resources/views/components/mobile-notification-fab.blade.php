@props(['game', 'notifications', 'unreadCount' => 0])

{{-- Mobile Notification FAB + Modal (dashboard only) --}}
<div class="md:hidden" x-data>
    {{-- Floating Bell Button --}}
    <button
        @click="$dispatch('open-modal', 'notifications-mobile')"
        class="fixed bottom-6 right-6 z-40 w-14 h-14 rounded-full bg-surface-700 border border-border-strong shadow-xl flex items-center justify-center text-text-secondary active:bg-surface-600 transition-colors"
        aria-label="{{ __('notifications.inbox') }}"
    >
        {{-- Bell icon --}}
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
        </svg>

        {{-- Unread badge --}}
        @if($unreadCount > 0)
        <span class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-accent-red text-white text-[10px] font-bold flex items-center justify-center">
            {{ $unreadCount > 9 ? '9+' : $unreadCount }}
        </span>
        @endif
    </button>

    {{-- Notifications Modal --}}
    <x-modal name="notifications-mobile" maxWidth="lg">
        <x-modal-header modalName="notifications-mobile">{{ __('notifications.inbox') }}</x-modal-header>

        {{-- Toolbar: unread count + mark all read --}}
        @if($unreadCount > 0)
        <div class="px-4 py-2.5 border-b border-border-default flex items-center justify-between">
            <span class="px-1.5 py-0.5 rounded-full bg-accent-blue/10 text-[10px] font-semibold text-accent-blue">
                {{ $unreadCount }} {{ __('notifications.new') }}
            </span>
            <form action="{{ route('game.notifications.read-all', $game->id) }}" method="POST">
                @csrf
                <button type="submit" class="text-[10px] text-accent-blue hover:text-blue-400 transition-colors">
                    {{ __('notifications.mark_all_read') }}
                </button>
            </form>
        </div>
        @endif

        {{-- Notification list --}}
        <div class="max-h-[70vh] overflow-y-auto">
            @if($notifications->isEmpty())
            <div class="text-center py-8 px-4">
                <div class="text-text-faint mb-2">
                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <p class="text-xs text-text-muted">{{ __('notifications.all_caught_up') }}</p>
            </div>
            @else
            <div class="divide-y divide-border-default">
                @foreach($notifications as $notification)
                    <x-notification-row :notification="$notification" :game="$game" />
                @endforeach
            </div>
            @endif
        </div>
    </x-modal>
</div>
