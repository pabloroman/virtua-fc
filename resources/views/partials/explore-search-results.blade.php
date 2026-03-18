@php
/** @var \Illuminate\Support\Collection<App\Models\GamePlayer> $players */
/** @var App\Models\Game $game */
/** @var string $query */
@endphp

{{-- Header --}}
<div class="flex items-center gap-4 mb-5 pb-4 border-b border-border-default">
    <div class="w-14 h-14 md:w-16 md:h-16 shrink-0 flex items-center justify-center bg-surface-700 rounded-xl">
        <svg class="w-8 h-8 text-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
    </div>
    <div class="min-w-0">
        <h3 class="text-lg font-bold text-text-primary">{{ __('transfers.explore_search_results_title') }}</h3>
        <p class="text-sm text-text-muted">{{ $players->count() }} {{ __('app.players') }}</p>
    </div>
</div>

@if($players->isEmpty())
    <p class="text-sm text-text-secondary text-center py-8">{{ __('transfers.explore_search_no_results') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b border-border-default">
                    <th class="py-2.5 pl-4 w-12"></th>
                    <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider"></th>
                    <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('transfers.explore_search_team') }}</th>
                    <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider text-center hidden md:table-cell">{{ __('transfers.explore_age') }}</th>
                    <th class="py-2.5 text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('transfers.explore_value') }}</th>
                    <th class="py-2.5 pr-4 w-10"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($players as $gp)
                <tr class="border-b border-border-default transition-colors hover:bg-[rgba(59,130,246,0.05)]">
                    {{-- Position badge --}}
                    <td class="py-2.5 pl-4">
                        <x-position-badge :position="$gp->position" size="sm" />
                    </td>
                    {{-- Name + nationality + mobile details --}}
                    <td class="py-2.5 pr-3">
                        <div class="flex items-center gap-2">
                            @if($gp->nationality_flag['code'] ?? null)
                            <img src="{{ Storage::disk('assets')->url('flags/' . $gp->nationality_flag['code'] . '.svg') }}" class="w-4 h-3 rounded-xs shadow-xs shrink-0" title="{{ $gp->nationality_flag['name'] }}">
                            @endif
                            <span class="font-medium text-text-primary truncate">{{ $gp->name }}</span>
                        </div>
                        {{-- Mobile-only details --}}
                        <div class="md:hidden text-xs text-text-muted mt-0.5 flex items-center gap-1 flex-wrap">
                            @if($gp->team)
                                <span class="truncate">{{ $gp->team->name }}</span>
                                <span>&middot;</span>
                            @else
                                <span>{{ __('transfers.free_agent') }}</span>
                                <span>&middot;</span>
                            @endif
                            <span>{{ $gp->age($game->current_date) }} {{ __('app.years') }}</span>
                            <span>&middot;</span>
                            <span>{{ \App\Support\Money::format($gp->market_value_cents) }}</span>
                        </div>
                    </td>
                    {{-- Team --}}
                    <td class="py-2.5 pr-3 hidden md:table-cell">
                        @if($gp->team)
                            <div class="flex items-center gap-2">
                                <img src="{{ $gp->team->image }}" alt="{{ $gp->team->name }}" class="w-5 h-5 shrink-0 object-contain">
                                <span class="text-text-secondary truncate">{{ $gp->team->name }}</span>
                            </div>
                        @else
                            <span class="text-text-muted">{{ __('transfers.free_agent') }}</span>
                        @endif
                    </td>
                    {{-- Age --}}
                    <td class="py-2.5 pr-3 hidden md:table-cell text-center text-text-secondary tabular-nums">{{ $gp->age($game->current_date) }}</td>
                    {{-- Market value --}}
                    <td class="py-2.5 pr-3 hidden md:table-cell text-text-secondary tabular-nums">{{ \App\Support\Money::format($gp->market_value_cents) }}</td>
                    {{-- Shortlist star --}}
                    <td class="py-2.5 pr-4 text-center"
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
                        <x-icon-button @click.prevent="toggle()"
                                class="rounded-full"
                                x-bind:class="isShortlisted ? 'text-accent-gold hover:text-amber-400' : 'text-text-body hover:text-accent-gold'"
                                x-bind:title="isShortlisted ? @js(__('transfers.remove_from_shortlist')) : @js(__('transfers.add_to_shortlist'))">
                            <svg class="w-5 h-5" :fill="isShortlisted ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </x-icon-button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
