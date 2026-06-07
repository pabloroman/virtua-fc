@props([
    'offer',   // array: id, sponsor_name, proposed_stadium_name, annual_value_cents, contract_seasons, estimated_annual_cents, cap_delta_cents
    'game',    // Game model (for the accept route)
])

@use(App\Support\Money)

<div class="bg-surface-700 border border-border-default rounded-xl overflow-hidden flex flex-col">
    {{-- Header: sponsor + the stadium name it imposes --}}
    <div class="px-5 pt-5 pb-3">
        <div class="font-heading text-base font-semibold text-text-primary truncate">{{ $offer['sponsor_name'] }}</div>
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
            <span class="font-semibold uppercase tracking-wide text-[10px] text-text-muted flex items-center gap-1">
                {{ __('club.stadium.naming_rights.estimated_this_season') }}
                <x-info-icon :tooltip="__('club.stadium.naming_rights.estimated_tooltip')" />
            </span>
            <span class="text-text-primary tabular-nums">{{ Money::format($offer['estimated_annual_cents']) }}</span>
        </div>
        <div class="flex items-baseline justify-between gap-3">
            <span class="font-semibold uppercase tracking-wide text-[10px] text-text-muted">{{ __('club.stadium.naming_rights.contract_length') }}</span>
            <span class="text-text-primary">{{ trans_choice('club.stadium.naming_rights.seasons', $offer['contract_seasons'], ['count' => $offer['contract_seasons']]) }}</span>
        </div>
    </div>

    {{-- Salary-cap impact — the whole point of the lever: more recurring
         revenue lifts the wage ceiling. --}}
    <div class="px-5 pb-4">
        <div class="flex items-baseline justify-between gap-3 px-3 py-2 rounded-lg bg-accent-green/10 border border-accent-green/25">
            <span class="font-semibold uppercase tracking-wide text-[10px] text-accent-green flex items-center gap-1">
                {{ __('club.stadium.naming_rights.wage_room') }}
                <x-info-icon :tooltip="__('club.stadium.naming_rights.wage_room_tooltip')" />
            </span>
            <span class="font-heading text-base font-bold text-accent-green tabular-nums">+{{ Money::format($offer['cap_delta_cents']) }}</span>
        </div>
    </div>

    {{-- Fan-cost warning --}}
    <div class="px-5 pb-4">
        <p class="text-[11px] text-accent-gold leading-relaxed">{{ __('club.stadium.naming_rights.fan_cost_warning') }}</p>
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
