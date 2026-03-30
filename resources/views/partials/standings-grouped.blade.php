@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var \Illuminate\Support\Collection $groupedStandings */
/** @var array $teamForms */
@endphp

<div class="md:col-span-2 space-y-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($groupedStandings as $groupLabel => $groupStandings)
            <div>
                <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-fixed text-right">
                            <thead>
                            <tr class="border-b border-border-default">
                                <th class="text-[10px] text-text-muted uppercase tracking-wider text-left w-6 px-3 py-2.5"></th>
                                <th class="text-[10px] text-text-muted uppercase tracking-wider text-left px-2 py-2.5">{{ __('game.group') }} {{ $groupLabel }}</th>
                                <th class="text-[10px] text-text-muted uppercase tracking-wider w-6 px-2 py-2.5">{{ __('game.played_abbr') }}</th>
                                <th class="text-[10px] text-text-muted uppercase tracking-wider w-6 px-2 py-2.5 hidden md:table-cell">{{ __('game.won_abbr') }}</th>
                                <th class="text-[10px] text-text-muted uppercase tracking-wider w-6 px-2 py-2.5 hidden md:table-cell">{{ __('game.drawn_abbr') }}</th>
                                <th class="text-[10px] text-text-muted uppercase tracking-wider w-6 px-2 py-2.5 hidden md:table-cell">{{ __('game.lost_abbr') }}</th>
                                <th class="text-[10px] text-text-muted uppercase tracking-wider w-6 px-2 py-2.5">{{ __('game.goal_diff_abbr') }}</th>
                                <th class="text-[10px] text-text-muted uppercase tracking-wider w-6 px-2 py-2.5">{{ __('game.pts_abbr') }}</th>
                                @unless($game->isTournamentMode())
                                <th class="text-[10px] text-text-muted uppercase tracking-wider w-6 px-2 py-2.5 text-center whitespace-nowrap">{{ __('game.last_5') }}</th>
                                @endunless
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($groupStandings as $standing)
                                @php $isPlayer = $standing->team_id === $game->team_id; @endphp
                                <tr class="border-b border-border-default text-sm transition-colors hover:bg-[rgba(59,130,246,0.05)] @if($isPlayer) bg-accent-gold/10 @endif">
                                    <td class="align-middle whitespace-nowrap text-left px-3 py-2 text-text-primary font-semibold">
                                        {{ $standing->position }}
                                    </td>
                                    <td class="align-middle whitespace-nowrap py-2 px-2">
                                        <div class="flex items-center space-x-1.5 @if($isPlayer) font-semibold @endif">
                                            <x-team-crest :team="$standing->team" class="w-5 h-5 shrink-0" />
                                            <span class="text-text-primary truncate">{{ $standing->team->name }}</span>
                                        </div>
                                    </td>
                                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums">{{ $standing->played }}</td>
                                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums hidden md:table-cell">{{ $standing->won }}</td>
                                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums hidden md:table-cell">{{ $standing->drawn }}</td>
                                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums hidden md:table-cell">{{ $standing->lost }}</td>
                                    <td class="align-middle whitespace-nowrap px-2 py-2 text-text-secondary tabular-nums">{{ $standing->goal_difference }}</td>
                                    <td class="align-middle whitespace-nowrap px-2 py-2 font-semibold text-text-primary tabular-nums">{{ $standing->points }}</td>
                                    @unless($game->isTournamentMode())
                                    <td class="align-middle whitespace-nowrap px-2 py-2">
                                        <div class="flex justify-center">
                                            @foreach($teamForms[$standing->team_id] ?? [] as $result)
                                                <x-form-icon :result="$result" />
                                            @endforeach
                                        </div>
                                    </td>
                                    @endunless
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
