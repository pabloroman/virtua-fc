@php
/** @var App\Models\Game $game */
/** @var \Illuminate\Support\Collection $standings */
/** @var array $teamForms */
/** @var array $standingsZones */

$borderColorMap = [
    'blue-500' => 'border-l-4 border-l-blue-500',
    'orange-500' => 'border-l-4 border-l-orange-500',
    'red-500' => 'border-l-4 border-l-red-500',
    'green-300' => 'border-l-4 border-l-green-300',
    'green-500' => 'border-l-4 border-l-green-500',
    'yellow-500' => 'border-l-4 border-l-yellow-500',
];

$bgColorMap = [
    'bg-blue-500' => 'bg-blue-500',
    'bg-orange-500' => 'bg-orange-500',
    'bg-red-500' => 'bg-red-500',
    'bg-green-300' => 'bg-green-300',
    'bg-green-500' => 'bg-green-500',
    'bg-yellow-500' => 'bg-yellow-500',
];

$getZoneClass = function($position) use ($standingsZones, $borderColorMap) {
    foreach ($standingsZones as $zone) {
        if ($position >= $zone['minPosition'] && $position <= $zone['maxPosition']) {
            return $borderColorMap[$zone['borderColor']] ?? '';
        }
    }
    return '';
};
@endphp

<div class="overflow-x-auto">
<table class="min-w-full table-fixed text-right divide-y divide-slate-300 border-spacing-2">
    <thead>
    <tr>
        <th class="font-semibold text-left w-8 p-2"></th>
        <th class="font-semibold text-left p-2"></th>
        <th class="font-semibold w-8 p-2">{{ __('game.played_abbr') }}</th>
        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.won_abbr') }}</th>
        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.drawn_abbr') }}</th>
        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.lost_abbr') }}</th>
        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.goals_for_abbr') }}</th>
        <th class="font-semibold w-8 p-2 hidden md:table-cell">{{ __('game.goals_against_abbr') }}</th>
        <th class="font-semibold w-8 p-2">{{ __('game.goal_diff_abbr') }}</th>
        <th class="font-semibold w-8 p-2">{{ __('game.pts_abbr') }}</th>
        <th class="font-semibold w-8 p-2 text-center">{{ __('game.last_5') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($standings as $standing)
        @php
            $isPlayer = $standing->team_id === $game->team_id;
            $zoneClass = $getZoneClass($standing->position);
        @endphp
        <tr class="border-b px-2 text-lg {{ $zoneClass }} @if($isPlayer) bg-amber-50 @endif">
            <td class="align-middle whitespace-nowrap text-left px-2 text-slate-900 font-semibold">
                <div class="flex items-center gap-1">
                    <span>{{ $standing->position }}</span>
                    @if($standing->position_change !== 0)
                        <span class="text-xs @if($standing->position_change > 0) text-green-500 @else text-red-500 @endif">
                            {{ $standing->position_change_icon }}
                        </span>
                    @endif
                </div>
            </td>
            <td class="align-middle whitespace-nowrap py-1.5 px-2">
                <div class="flex items-center space-x-2 @if($isPlayer) font-semibold @endif">
                    <img src="{{ $standing->team->image }}" class="w-6 h-6">
                    <span>{{ $standing->team->name }}</span>
                </div>
            </td>
            <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->played }}</td>
            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->won }}</td>
            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->drawn }}</td>
            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->lost }}</td>
            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->goals_for }}</td>
            <td class="align-middle whitespace-nowrap p-2 text-slate-400 hidden md:table-cell">{{ $standing->goals_against }}</td>
            <td class="align-middle whitespace-nowrap p-2 text-slate-400">{{ $standing->goal_difference }}</td>
            <td class="align-middle whitespace-nowrap p-2 font-semibold">{{ $standing->points }}</td>
            <td class="align-middle whitespace-nowrap p-2">
                <div class="flex justify-center">
                    @foreach($teamForms[$standing->team_id] ?? [] as $result)
                        <x-form-icon :result="$result" />
                    @endforeach
                </div>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

@if(count($standingsZones) > 0)
    <div class="flex gap-6 text-xs text-slate-500">
        @foreach($standingsZones as $zone)
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 {{ $bgColorMap[$zone['bgColor']] ?? '' }} rounded"></div>
                <span>{{ __($zone['label']) }}</span>
            </div>
        @endforeach
    </div>
@endif
