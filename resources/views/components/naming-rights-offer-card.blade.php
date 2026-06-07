@props([
    'offer',   // array: id, sponsor_name, proposed_stadium_name, annual_value_cents, contract_seasons
    'game',    // Game model (for the accept route)
])

@use(App\Support\Money)

<div class="bg-surface-700 border border-border-default rounded-xl overflow-hidden flex flex-col">
    {{-- Header: sponsor + the stadium name it imposes --}}
    <div class="px-5 pt-5 pb-3">
        <div class="font-heading text-xl font-bold text-text-primary truncate">{{ $offer['sponsor_name'] }}</div>
        <div class="mt-1 text-xs text-text-secondary truncate">
            {{ __('club.stadium.naming_rights.becomes', ['name' => $offer['proposed_stadium_name']]) }}
        </div>
    </div>

    {{-- Terms --}}
    <div class="px-5 pb-4 space-y-1.5 text-sm">
        <div class="flex items-baseline justify-between gap-3">
            <span class="font-semibold uppercase tracking-wide text-[10px] text-text-muted">{{ __('club.stadium.naming_rights.annual_value') }}</span>
            <span class="font-heading text-base font-bold text-accent-green tabular-nums">{{ Money::format($offer['annual_value_cents']) }}</span>
        </div>
        <div class="flex items-baseline justify-between gap-3">
            <span class="font-semibold uppercase tracking-wide text-[10px] text-text-muted">{{ __('club.stadium.naming_rights.contract_length') }}</span>
            <span class="text-text-primary">{{ trans_choice('club.stadium.naming_rights.seasons', $offer['contract_seasons'], ['count' => $offer['contract_seasons']]) }}</span>
        </div>
    </div>

    {{-- Accept CTA --}}
    <div class="px-5 pb-5 mt-auto">
        <form method="POST" action="{{ route('game.club.commercial.naming-rights.accept', $game->id) }}"
              onsubmit="return confirm(@js(__('club.stadium.naming_rights.accept_confirm', ['sponsor' => $offer['sponsor_name']])))">
            @csrf
            <input type="hidden" name="deal_id" value="{{ $offer['id'] }}">
            <x-primary-button color="green" size="sm" class="w-full">
                {{ __('club.stadium.naming_rights.accept_button') }}
            </x-primary-button>
        </form>
    </div>
</div>
