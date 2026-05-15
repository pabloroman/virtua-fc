@php
/** @var App\Models\Game $game */
/** @var \Illuminate\Support\Collection $entries */
/** @var bool $hasOnlyInProgress */
/** @var array<string, string> $gradeBadgeClasses */
/** @var array<string, string> $endReasonLabels */
/** @var array<string, string> $endReasonClasses */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('manager.career_title') }}</h2>
            <p class="mt-2 text-sm text-text-secondary">{{ __('manager.career_intro') }}</p>
        </div>

        <x-section-card :title="__('manager.career_title')">
            @if($entries->isEmpty())
                <div class="p-6 text-sm text-text-muted text-center">
                    {{ __('manager.career_empty') }}
                </div>
            @else
                {{-- Mobile: stacked cards --}}
                <div class="md:hidden divide-y divide-border-default">
                    @foreach($entries as $entry)
                        <div class="p-4 {{ $entry['in_progress'] ? 'border-l-2 border-l-accent-blue' : '' }}">
                            <div class="flex items-start gap-3">
                                @if($entry['team'])
                                    <div class="shrink-0 w-12 h-12 flex items-center justify-center bg-surface-700 rounded-lg overflow-hidden">
                                        <x-team-crest :team="$entry['team']" class="w-10 h-10 object-contain" />
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="font-heading text-base font-semibold text-text-primary truncate">{{ $entry['team']?->name ?? '—' }}</div>
                                        <span class="shrink-0 text-[10px] uppercase tracking-widest text-text-muted">{{ $entry['season_label'] }}</span>
                                    </div>
                                    <div class="text-xs text-text-secondary truncate">{{ $entry['competition'] ? __($entry['competition']->name) : '—' }}</div>
                                </div>
                            </div>

                            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                <div>
                                    <dt class="text-[10px] uppercase tracking-widest text-text-muted">{{ __('manager.career_col_goal') }}</dt>
                                    <dd class="text-text-primary">{{ $entry['season_goal_label'] ?? __('manager.career_no_goal') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] uppercase tracking-widest text-text-muted">{{ __('manager.career_col_position') }}</dt>
                                    <dd class="text-text-primary">{{ $entry['final_position'] ?? __('manager.career_no_position') }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-[10px] uppercase tracking-widest text-text-muted">{{ __('manager.career_col_outcome') }}</dt>
                                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                                        @if($entry['in_progress'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wider bg-accent-blue/10 text-accent-blue border border-accent-blue/30">
                                                {{ __('manager.career_in_progress') }}
                                            </span>
                                        @elseif($entry['goal_grade'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wider {{ $gradeBadgeClasses[$entry['goal_grade']] ?? 'bg-surface-700 text-text-secondary border border-border-default' }}">
                                                {{ __('season.evaluation_' . $entry['goal_grade']) }}
                                            </span>
                                        @else
                                            <span class="text-text-muted">—</span>
                                        @endif
                                        @if(!$entry['in_progress'] && $entry['end_reason'] && isset($endReasonLabels[$entry['end_reason']]))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wider {{ $endReasonClasses[$entry['end_reason']] }}">
                                                {{ $endReasonLabels[$entry['end_reason']] }}
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach
                </div>

                {{-- Tablet/desktop: table --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-default">
                        <thead>
                            <tr class="text-left">
                                <th class="px-5 py-3 text-[10px] uppercase tracking-widest text-text-muted font-semibold">{{ __('manager.career_col_season') }}</th>
                                <th class="px-5 py-3 text-[10px] uppercase tracking-widest text-text-muted font-semibold">{{ __('manager.career_col_club') }}</th>
                                <th class="px-5 py-3 text-[10px] uppercase tracking-widest text-text-muted font-semibold">{{ __('manager.career_col_goal') }}</th>
                                <th class="px-5 py-3 text-[10px] uppercase tracking-widest text-text-muted font-semibold text-center">{{ __('manager.career_col_position') }}</th>
                                <th class="px-5 py-3 text-[10px] uppercase tracking-widest text-text-muted font-semibold">{{ __('manager.career_col_outcome') }}</th>
                                <th class="px-5 py-3 text-[10px] uppercase tracking-widest text-text-muted font-semibold">{{ __('manager.career_col_end') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-default">
                            @foreach($entries as $entry)
                                <tr class="{{ $entry['in_progress'] ? 'bg-accent-blue/5' : '' }}">
                                    <td class="px-5 py-3 text-sm text-text-primary whitespace-nowrap font-semibold">{{ $entry['season_label'] }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            @if($entry['team'])
                                                <div class="shrink-0 w-9 h-9 flex items-center justify-center bg-surface-700 rounded-lg overflow-hidden">
                                                    <x-team-crest :team="$entry['team']" class="w-7 h-7 object-contain" />
                                                </div>
                                            @endif
                                            <div class="min-w-0">
                                                <div class="text-sm font-semibold text-text-primary truncate">{{ $entry['team']?->name ?? '—' }}</div>
                                                <div class="text-xs text-text-muted truncate">{{ $entry['competition'] ? __($entry['competition']->name) : '—' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-text-primary">{{ $entry['season_goal_label'] ?? __('manager.career_no_goal') }}</td>
                                    <td class="px-5 py-3 text-sm text-text-primary text-center whitespace-nowrap">{{ $entry['final_position'] ?? __('manager.career_no_position') }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if($entry['in_progress'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wider bg-accent-blue/10 text-accent-blue border border-accent-blue/30">
                                                {{ __('manager.career_in_progress') }}
                                            </span>
                                        @elseif($entry['goal_grade'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wider {{ $gradeBadgeClasses[$entry['goal_grade']] ?? 'bg-surface-700 text-text-secondary border border-border-default' }}">
                                                {{ __('season.evaluation_' . $entry['goal_grade']) }}
                                            </span>
                                        @else
                                            <span class="text-text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if(!$entry['in_progress'] && $entry['end_reason'] && isset($endReasonLabels[$entry['end_reason']]))
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold uppercase tracking-wider {{ $endReasonClasses[$entry['end_reason']] }}">
                                                {{ $endReasonLabels[$entry['end_reason']] }}
                                            </span>
                                        @else
                                            <span class="text-text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($hasOnlyInProgress)
                    <div class="px-5 py-3 border-t border-border-default text-xs text-text-muted">
                        {{ __('manager.career_empty') }}
                    </div>
                @endif
            @endif
        </x-section-card>
    </div>
</x-app-layout>
