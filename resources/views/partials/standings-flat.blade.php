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
    'bg-accent-blue' => 'bg-accent-blue',
    'bg-orange-500' => 'bg-orange-500',
    'bg-accent-red' => 'bg-accent-red',
    'bg-green-300' => 'bg-green-300',
    'bg-accent-green' => 'bg-accent-green',
    'bg-accent-gold' => 'bg-accent-gold',
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

<div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full table-fixed text-right">
            <thead>
            <tr class="border-b border-border-default">
                <th class="text-[10px] text-text-muted uppercase tracking-wider text-left w-8 px-3 py-2.5"></th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider text-left px-2 py-2.5"></th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5">{{ __('game.played_abbr') }}</th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5 hidden md:table-cell">{{ __('game.won_abbr') }}</th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5 hidden md:table-cell">{{ __('game.drawn_abbr') }}</th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5 hidden md:table-cell">{{ __('game.lost_abbr') }}</th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5 hidden md:table-cell">{{ __('game.goals_for_abbr') }}</th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5 hidden md:table-cell">{{ __('game.goals_against_abbr') }}</th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5">{{ __('game.goal_diff_abbr') }}</th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5">{{ __('game.pts_abbr') }}</th>
                <th class="text-[10px] text-text-muted uppercase tracking-wider w-8 px-2 py-2.5 text-center whitespace-nowrap">{{ __('game.last_5') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($standings as $standing)
                @php
                    $isPlayer = $standing->team_id === $game->team_id;
                    $zoneClass = $getZoneClass($standing->position);
                @endphp
                <tr class="border-b border-border-default text-sm transition-colors hover:bg-[rgba(59,130,246,0.05)] {{ $zoneClass }} @if($isPlayer) bg-accent-gold/10 @endif">
                    <td class="align-middle whitespace-nowrap text-left px-3 py-2 text-text-primary font-semibold">
                        <div class="flex items-center gap-1">
                            <span>{{ $standing->position }}</span>
                            @if($standing->position_change !== 0)
                                <span class="text-xs @if($standing->position_change > 0) text-accent-green @else text-accent-red @endif">
                                    {{ $standing->position_change_icon }}
                                </span>
                            @endif
                        </div>
                    </td>
                    <td class="align-middle whitespace-nowrap py-2 px-2">
                        <div class="flex items-center space-x-2 @if($isPlayer) font-semibold @endif">
                            <x-team-crest :team="$standing->team" class="w-6 h-6 shrink-0" />
                            <span class="text-text-primary truncate">{{ $standing->team->name }}</span>
                        </div>
                    </td>
                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums">{{ $standing->played }}</td>
                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums hidden md:table-cell">{{ $standing->won }}</td>
                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums hidden md:table-cell">{{ $standing->drawn }}</td>
                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums hidden md:table-cell">{{ $standing->lost }}</td>
                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums hidden md:table-cell">{{ $standing->goals_for }}</td>
                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums hidden md:table-cell">{{ $standing->goals_against }}</td>
                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums">{{ $standing->goal_difference }}</td>
                    <td class="align-middle whitespace-nowrap px-2 py-2 font-semibold text-text-primary tabular-nums">{{ $standing->points }}</td>
                    <td class="align-middle whitespace-nowrap px-2 py-2">
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
</div>

@if(count($standingsZones) > 0)
    <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-text-muted mt-3 px-1">
        @foreach($standingsZones as $zone)
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 {{ $bgColorMap[$zone['bgColor']] ?? '' }} rounded-sm"></div>
                <span>{{ __($zone['label']) }}</span>
            </div>
        @endforeach
    </div>
@endif
