@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var \Illuminate\Support\Collection $groupedStandings */
/** @var array $teamForms */
@endphp

<div class="md:col-span-2 space-y-6">
    <h3 class="font-semibold text-xl text-slate-900">{{ $competition->name }}</h3>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($groupedStandings as $groupLabel => $groupStandings)
            <div class="space-y-2">
                <h4 class="font-semibold text-lg text-slate-800">{{ __('game.group') }} {{ $groupLabel }}</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-fixed text-right divide-y divide-slate-300">
                        <thead>
                        <tr>
                            <th class="font-semibold text-left w-6 p-1.5 text-xs"></th>
                            <th class="font-semibold text-left p-1.5 text-xs"></th>
                            <th class="font-semibold w-6 p-1.5 text-xs">{{ __('game.played_abbr') }}</th>
                            <th class="font-semibold w-6 p-1.5 text-xs hidden md:table-cell">{{ __('game.won_abbr') }}</th>
                            <th class="font-semibold w-6 p-1.5 text-xs hidden md:table-cell">{{ __('game.drawn_abbr') }}</th>
                            <th class="font-semibold w-6 p-1.5 text-xs hidden md:table-cell">{{ __('game.lost_abbr') }}</th>
                            <th class="font-semibold w-6 p-1.5 text-xs">{{ __('game.goal_diff_abbr') }}</th>
                            <th class="font-semibold w-6 p-1.5 text-xs">{{ __('game.pts_abbr') }}</th>
                            <th class="font-semibold w-6 p-1.5 text-xs text-center">{{ __('game.last_5') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($groupStandings as $standing)
                            @php $isPlayer = $standing->team_id === $game->team_id; @endphp
                            <tr class="border-b text-sm @if($isPlayer) bg-amber-50 @endif">
                                <td class="align-middle whitespace-nowrap text-left px-1.5 text-slate-900 font-semibold">
                                    {{ $standing->position }}
                                </td>
                                <td class="align-middle whitespace-nowrap py-1 px-1.5">
                                    <div class="flex items-center space-x-1.5 @if($isPlayer) font-semibold @endif">
                                        <img src="{{ $standing->team->image }}" class="w-5 h-5 shrink-0">
                                        <span class="truncate">{{ $standing->team->name }}</span>
                                    </div>
                                </td>
                                <td class="align-middle whitespace-nowrap p-1.5 text-slate-400">{{ $standing->played }}</td>
                                <td class="align-middle whitespace-nowrap p-1.5 text-slate-400 hidden md:table-cell">{{ $standing->won }}</td>
                                <td class="align-middle whitespace-nowrap p-1.5 text-slate-400 hidden md:table-cell">{{ $standing->drawn }}</td>
                                <td class="align-middle whitespace-nowrap p-1.5 text-slate-400 hidden md:table-cell">{{ $standing->lost }}</td>
                                <td class="align-middle whitespace-nowrap p-1.5 text-slate-400">{{ $standing->goal_difference }}</td>
                                <td class="align-middle whitespace-nowrap p-1.5 font-semibold">{{ $standing->points }}</td>
                                <td class="align-middle whitespace-nowrap p-1.5">
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
        @endforeach
    </div>
</div>
