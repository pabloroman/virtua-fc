@php
    /** @var App\Models\Game $game **/
    use App\Modules\Lineup\RotationPolicy;
    $currentRotationPolicy = $game->tactics?->default_rotation_policy ?? RotationPolicy::Balanced;
@endphp
<x-modal name="fast-mode-info" maxWidth="md">
    <x-modal-header modalName="fast-mode-info">{{ __('game.fast_mode_title') }}</x-modal-header>
    <form action="{{ route('game.fast-mode.enter', $game->id) }}" method="POST">
        @csrf
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

            <div
                x-data="{ policy: @js($currentRotationPolicy->value) }"
                class="space-y-1.5"
            >
                <label for="rotation_policy" class="block text-xs font-medium uppercase tracking-wide text-text-muted">
                    {{ __('game.fast_mode_rotation_policy') }}
                </label>
                <x-select-input
                    id="rotation_policy"
                    name="rotation_policy"
                    x-model="policy"
                    class="w-full"
                >
                    @foreach (RotationPolicy::cases() as $policy)
                        <option value="{{ $policy->value }}">{{ __($policy->label()) }}</option>
                    @endforeach
                </x-select-input>
                <p class="text-xs text-text-muted leading-relaxed">
                    @foreach (RotationPolicy::cases() as $policy)
                        <span x-show="policy === @js($policy->value)" x-cloak>{{ __($policy->description()) }}</span>
                    @endforeach
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-border-default">
                <x-secondary-button type="button" @click="$dispatch('close-modal', 'fast-mode-info')">
                    {{ __('app.cancel') }}
                </x-secondary-button>
                <x-primary-button color="blue">
                    {{ __('game.fast_mode_enter') }}
                </x-primary-button>
            </div>
        </div>
    </form>
</x-modal>
