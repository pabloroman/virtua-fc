@php /** @var App\Models\Game $game **/ @endphp
<x-modal name="fast-mode-info" maxWidth="md">
    <x-modal-header modalName="fast-mode-info">{{ __('game.fast_mode_title') }}</x-modal-header>
    <div class="p-4 md:p-6 space-y-4">
        <div class="flex items-start gap-3">
            <div class="shrink-0 w-10 h-10 rounded-full bg-accent-blue/10 text-accent-blue flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <p class="text-sm text-text-body leading-relaxed">
                {{ __('game.fast_mode_explanation') }}
            </p>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-border-default">
            <x-secondary-button @click="$dispatch('close-modal', 'fast-mode-info')">
                {{ __('app.cancel') }}
            </x-secondary-button>
            <form action="{{ route('game.fast-mode.enter', $game->id) }}" method="POST">
                @csrf
                <x-primary-button color="blue">
                    {{ __('game.fast_mode_enter') }}
                </x-primary-button>
            </form>
        </div>
    </div>
</x-modal>
