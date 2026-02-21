@props(['game', 'player', 'unavailable' => false, 'ageClass' => '', 'sticky' => false])

{{-- Position --}}
<td class="py-2 text-center {{ $sticky ? 'sticky left-0 bg-white z-10' : '' }}">
    <x-position-badge :position="$player->position" :tooltip="\App\Support\PositionMapper::toDisplayName($player->position)" class="cursor-help" />
</td>
{{-- Number --}}
<td class="py-2 text-center text-slate-400 text-xs hidden md:table-cell">{{ $player->number ?? '-' }}</td>
{{-- Name --}}
<td class="py-2 {{ $sticky ? 'sticky left-10 bg-white z-10' : '' }}">
    <div class="flex items-center space-x-2">
        <button onclick="window.dispatchEvent(new CustomEvent('show-player-detail', { detail: '{{ route('game.player.detail', [$game->id, $player->id]) }}' }))" class="p-1.5 text-slate-300 rounded hover:text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
            </svg>
        </button>
        <div>
            <div class="font-medium truncate {{ $unavailable ? 'text-slate-400' : 'text-slate-900' }}">
                {{ $player->name }}
            </div>
            {{ $nameExtra ?? '' }}
        </div>
    </div>
</td>
{{-- Status icon --}}
<td class="py-2 text-center">
    {{ $status ?? '' }}
</td>
{{-- Nationality --}}
<td class="py-2 text-center hidden md:table-cell">
    @if($player->nationality_flag)
        <img src="/flags/{{ $player->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded shadow-sm" title="{{ $player->nationality_flag['name'] }}">
    @endif
</td>
{{-- Age --}}
<td class="py-2 text-center hidden md:table-cell"><span class="{{ $ageClass }}">{{ $player->age }}</span></td>
{{-- Ghost spacer --}}
<td class="py-2"></td>
