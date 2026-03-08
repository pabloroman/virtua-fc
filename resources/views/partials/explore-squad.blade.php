@php
/** @var App\Models\Team $team */
/** @var \Illuminate\Support\Collection<App\Models\GamePlayer> $players */
/** @var App\Models\Game $game */
@endphp

{{-- Team header --}}
<div class="flex items-center gap-4 mb-5 pb-4 border-b border-slate-100">
    <img src="{{ $team->image }}" alt="{{ $team->name }}" class="w-14 h-14 md:w-16 md:h-16 shrink-0 object-contain">
    <div class="min-w-0">
        <h3 class="text-lg font-bold text-slate-900 truncate">{{ $team->name }}</h3>
    </div>
</div>

{{-- Scouting nudge --}}
<div class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 mb-5">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span>{{ __('transfers.explore_scouting_nudge') }}</span>
</div>

{{-- Squad table --}}
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-slate-400 border-b border-slate-100">
                <th class="pb-2 font-medium"></th>
                <th class="pb-2 font-medium"></th>
                <th class="pb-2 font-medium hidden md:table-cell">{{ __('transfers.explore_age') }}</th>
                <th class="pb-2 font-medium hidden md:table-cell">{{ __('transfers.explore_value') }}</th>
                <th class="pb-2 font-medium hidden md:table-cell">{{ __('transfers.explore_contract_year') }}</th>
                <th class="pb-2 font-medium w-10"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($players as $gp)
            <tr class="border-b border-slate-50 hover:bg-slate-50/50">
                {{-- Position badge --}}
                <td class="py-0 p-2 w-12">
                    <x-position-badge :position="$gp->position" size="sm" />
                </td>
                {{-- Name + nationality + mobile details --}}
                <td class="py-0 pr-3">
                    <div class="flex items-center gap-2">
                        @if($gp->nationality_flag['code'] ?? null)
                        <img src="/flags/{{ $gp->nationality_flag['code'] }}.svg" class="w-4 h-3 rounded-sm shadow-sm shrink-0" title="{{ $gp->nationality_flag['name'] }}">
                        @endif
                        <span class="font-medium text-slate-900 truncate">{{ $gp->name }}</span>
                        @if($gp->is_loaned_in)
                        <span class="text-[10px] bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded font-medium shrink-0">{{ __('transfers.loans') }}</span>
                        @endif
                    </div>
                    {{-- Mobile-only details --}}
                    <div class="md:hidden text-xs text-slate-400 mt-0.5">
                        <span>{{ $gp->age($game->current_date) }} {{ __('app.years') }}</span>
                        <span class="mx-1">&middot;</span>
                        <span>{{ \App\Support\Money::format($gp->market_value_cents) }}</span>
                        <span class="mx-1">&middot;</span>
                        <span>{{ $gp->contract_until?->year ?? '—' }}</span>
                    </div>
                </td>
                {{-- Age --}}
                <td class="py-0 pr-3 hidden md:table-cell text-slate-600">{{ $gp->age($game->current_date) }}</td>
                {{-- Market value --}}
                <td class="py-0 pr-3 hidden md:table-cell text-slate-600 font-medium">{{ \App\Support\Money::format($gp->market_value_cents) }}</td>
                {{-- Contract --}}
                <td class="py-0 pr-3 hidden md:table-cell text-slate-500">{{ $gp->contract_until?->year ?? '—' }}</td>
                {{-- Shortlist star --}}
                <td class="py-0 text-center"
                    x-data="{
                        isShortlisted: {{ $gp->is_shortlisted ? 'true' : 'false' }},
                        async toggle() {
                            try {
                                const response = await fetch('{{ route('game.scouting.shortlist.toggle', [$game->id, $gp->id]) }}', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    },
                                });
                                const data = await response.json();
                                if (data.success) {
                                    this.isShortlisted = data.action === 'added';
                                } else if (data.message) {
                                    alert(data.message);
                                }
                            } catch (e) {}
                        }
                    }">
                    <button @click.prevent="toggle()"
                            class="p-1.5 rounded-full transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
                            :class="isShortlisted ? 'text-amber-500 hover:text-amber-600' : 'text-slate-300 hover:text-amber-400'"
                            :title="isShortlisted ? '{{ __('transfers.remove_from_shortlist') }}' : '{{ __('transfers.add_to_shortlist') }}'">
                        <svg class="w-5 h-5" :fill="isShortlisted ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
