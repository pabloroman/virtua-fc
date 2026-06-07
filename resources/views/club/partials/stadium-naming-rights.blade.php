{{-- Stadium identity: the current ground name and the cosmetic rename lever
     (gated to the pre-season window, once per season). The naming-rights
     *sponsorship* lever — selling the name for recurring income —
     lives on the Commercial page; when a sponsor owns the name this panel just
     points there. Rendered as the body of the `stadium-identity` modal (title
     comes from the modal header), so it has no card wrapper of its own. --}}
<div class="px-5 py-4">
    <p class="text-xs text-text-muted leading-relaxed mb-4">
        {{ __('club.stadium.identity.subtitle') }}
    </p>

    {{-- Current identity --}}
    <div class="flex items-center justify-between gap-3 px-3.5 py-3 bg-surface-700 border border-border-default rounded-lg">
        <div class="min-w-0">
            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.naming_rights.current_name') }}</div>
            <div class="font-heading text-base font-bold text-text-primary truncate">{{ $stadiumIdentity['currentName'] ?? '—' }}</div>
        </div>
        <span class="shrink-0 text-[10px] uppercase tracking-wide px-2 py-1 rounded-md bg-surface-600 text-text-secondary">
            {{ __('club.stadium.naming_rights.source_' . $stadiumIdentity['source']) }}
        </span>
    </div>

    @if($stadiumIdentity['hasActiveDeal'])
        {{-- A sponsor owns the name — rename is locked; manage the deal on the
             Commercial page. --}}
        <div class="mt-4 px-4 py-3 bg-surface-700 border border-border-default rounded-lg">
            <p class="text-[11px] text-text-secondary leading-relaxed">
                {{ __('club.stadium.identity.sponsor_owns_name', ['sponsor' => $stadiumIdentity['sponsorName']]) }}
            </p>
            <a href="{{ route('game.club.commercial', $game->id) }}"
               class="mt-2 inline-block text-[11px] font-semibold text-accent-blue hover:underline">
                {{ __('club.stadium.identity.manage_in_commercial') }} →
            </a>
        </div>
    @elseif($stadiumIdentity['windowOpen'])
        {{-- Cosmetic rename control --}}
        <div class="mt-4" x-data="{ renaming: false }">
            @if($stadiumIdentity['canRename'])
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
                           value="{{ $stadiumIdentity['currentName'] }}"
                           class="flex-1 bg-surface-700 border border-border-default rounded-lg px-3 py-2 text-sm text-text-primary focus:outline-hidden focus:ring-2 focus:ring-accent-blue"
                           placeholder="{{ __('club.stadium.naming_rights.rename_placeholder') }}">
                    <x-primary-button>{{ __('club.stadium.naming_rights.rename_save') }}</x-primary-button>
                    <x-secondary-button type="button" @click="renaming = false">{{ __('app.cancel') }}</x-secondary-button>
                </form>
            @else
                <p class="text-[11px] text-text-muted">{{ __('club.stadium.naming_rights.rename_locked_season') }}</p>
            @endif
        </div>

        {{-- Pointer to the sponsorship lever --}}
        <a href="{{ route('game.club.commercial', $game->id) }}"
           class="mt-4 inline-block text-[11px] font-semibold text-accent-blue hover:underline">
            {{ __('club.stadium.identity.sell_naming_rights') }} →
        </a>
    @else
        {{-- Window closed (league under way) --}}
        <p class="mt-4 text-[11px] text-accent-gold leading-relaxed">{{ __('club.stadium.naming_rights.window_closed_notice') }}</p>
    @endif
</div>
