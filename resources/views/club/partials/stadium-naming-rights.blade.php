@use(App\Support\Money)

{{-- Stadium identity & naming rights. Two levers, both gated to the
     pre-season window: a cosmetic rename and selling the name to a sponsor
     for attendance-scaled income (at a one-time hit to fan support).
     Rendered as the body of the `stadium-identity` modal (title comes from
     the modal header), so this partial has no card wrapper of its own. --}}
<div class="px-5 py-4">
    <p class="text-xs text-text-muted leading-relaxed mb-4">
        {{ __('club.stadium.naming_rights.subtitle') }}
    </p>

    {{-- Current identity --}}
    <div class="flex items-center justify-between gap-3 px-3.5 py-3 bg-surface-700 border border-border-default rounded-lg">
        <div class="min-w-0">
            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.naming_rights.current_name') }}</div>
            <div class="font-heading text-base font-bold text-text-primary truncate">{{ $namingRights['currentName'] ?? '—' }}</div>
        </div>
        <span class="shrink-0 text-[10px] uppercase tracking-wide px-2 py-1 rounded-md bg-surface-600 text-text-secondary">
            {{ __('club.stadium.naming_rights.source_' . $namingRights['source']) }}
        </span>
    </div>

    @if($namingRights['activeDeal'])
        {{-- Active sponsorship --}}
        @php($deal = $namingRights['activeDeal'])
        <div class="mt-4 px-4 py-4 bg-accent-green/10 border border-accent-green/30 rounded-lg">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm font-semibold text-text-primary">{{ $deal['sponsor_name'] }}</span>
                <span class="text-[11px] text-text-secondary">{{ trans_choice('club.stadium.naming_rights.seasons_remaining', $deal['seasons_remaining'], ['count' => $deal['seasons_remaining']]) }}</span>
            </div>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <div>
                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.naming_rights.headline_value') }}</div>
                    <div class="font-heading text-lg font-bold text-text-primary tabular-nums">{{ Money::format($deal['annual_value_cents']) }}</div>
                </div>
                <div>
                    <div class="text-[10px] text-text-muted uppercase tracking-widest flex items-center gap-1">
                        {{ __('club.stadium.naming_rights.estimated_this_season') }}
                        <x-info-icon :tooltip="__('club.stadium.naming_rights.estimated_tooltip')" />
                    </div>
                    <div class="font-heading text-lg font-bold text-accent-green tabular-nums">{{ Money::format($deal['estimated_annual_cents']) }}</div>
                </div>
            </div>
        </div>
    @elseif($namingRights['windowOpen'])
        {{-- Rename control --}}
        <div class="mt-4" x-data="{ renaming: false }">
            @if($namingRights['canRename'])
                <div x-show="!renaming">
                    <x-secondary-button type="button" @click="renaming = true">
                        {{ __('club.stadium.naming_rights.rename_button') }}
                    </x-secondary-button>
                </div>
                <form x-show="renaming" x-cloak method="POST"
                      action="{{ route('game.club.stadium.rename', $game->id) }}"
                      class="flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input type="text" name="name" maxlength="40" required
                           value="{{ $namingRights['currentName'] }}"
                           class="flex-1 bg-surface-700 border border-border-default rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-hidden focus:ring-2 focus:ring-accent-blue"
                           placeholder="{{ __('club.stadium.naming_rights.rename_placeholder') }}">
                    <x-primary-button>{{ __('club.stadium.naming_rights.rename_save') }}</x-primary-button>
                    <x-secondary-button type="button" @click="renaming = false">{{ __('app.cancel') }}</x-secondary-button>
                </form>
            @else
                <p class="text-[11px] text-text-muted">{{ __('club.stadium.naming_rights.rename_locked_season') }}</p>
            @endif
        </div>

        {{-- Competing sponsor offers --}}
        <div class="mt-6">
            <div class="text-[10px] text-text-muted uppercase tracking-widest mb-3">{{ __('club.stadium.naming_rights.offers_title') }}</div>
            @if(count($namingRights['offers']) > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($namingRights['offers'] as $offer)
                        <x-naming-rights-offer-card :offer="$offer" :game="$game" />
                    @endforeach
                </div>
            @else
                <p class="text-sm text-text-muted">{{ __('club.stadium.naming_rights.no_offers') }}</p>
            @endif
        </div>
    @else
        {{-- Window closed (league under way) --}}
        <p class="mt-4 text-[11px] text-accent-gold leading-relaxed">{{ __('club.stadium.naming_rights.window_closed_notice') }}</p>
    @endif
</div>
