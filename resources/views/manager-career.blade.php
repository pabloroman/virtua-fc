@php
/** @var App\Models\Game $game */
/** @var \Illuminate\Support\Collection $entries */
/** @var \Illuminate\Support\Collection $trophiesByStint */
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

        {{-- Trophies grouped by stint. Two non-consecutive spells at the
             same club appear as separate groups so each gets credit for
             only what was won during that tenure. --}}
        <div class="mt-6">
            <x-section-card :title="__('manager.career_trophies_title')">
                @if($trophiesByStint->isEmpty())
                    <div class="px-5 py-4">
                        <p class="text-sm text-text-muted leading-relaxed">{{ __('manager.career_trophies_empty') }}</p>
                    </div>
                @else
                    <div class="divide-y divide-border-default">
                        @foreach($trophiesByStint as $stint)
                            <div class="grid grid-cols-1 md:grid-cols-3">
                                {{-- Club header: full-width banner on mobile,
                                     left column on desktop. --}}
                                <div class="md:col-span-1 bg-surface-700/40 md:bg-transparent px-5 py-4 md:border-r md:border-border-default flex items-center gap-3">
                                    @if($stint['team'])
                                        <div class="shrink-0 w-11 h-11 flex items-center justify-center bg-surface-700 rounded-lg overflow-hidden">
                                            <x-team-crest :team="$stint['team']" class="w-9 h-9 object-contain" />
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="font-heading text-base font-semibold text-text-primary truncate">{{ $stint['team']?->name ?? '—' }}</div>
                                        <div class="text-[11px] uppercase tracking-widest text-text-muted mt-0.5">{{ $stint['season_range_label'] }}</div>
                                    </div>
                                </div>

                                {{-- Trophies for this stint --}}
                                <div class="md:col-span-2 px-5 py-4 space-y-2">
                                    @foreach($stint['trophies'] as $entry)
                                        @php
                                            $typeConfig = match($entry['trophy_type']) {
                                                'league' => ['color' => 'text-accent-gold', 'bg' => 'bg-accent-gold/15'],
                                                'cup' => ['color' => 'text-accent-blue', 'bg' => 'bg-accent-blue/15'],
                                                'european' => ['color' => 'text-accent-green', 'bg' => 'bg-accent-green/15'],
                                                'supercup' => ['color' => 'text-accent-orange', 'bg' => 'bg-accent-orange/15'],
                                                default => ['color' => 'text-text-muted', 'bg' => 'bg-surface-700'],
                                            };
                                        @endphp
                                        <div class="flex items-start gap-3">
                                            <div class="w-7 h-7 rounded-lg {{ $typeConfig['bg'] }} flex items-center justify-center shrink-0 mt-0.5">
                                                <svg class="w-3.5 h-3.5 {{ $typeConfig['color'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a.75.75 0 0 0 0 1.5h12.17a.75.75 0 0 0 0-1.5h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.707 6.707 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 3.246 2.632 5.88 5.834 5.88 3.203 0 5.834-2.634 5.834-5.88V3.357a47.62 47.62 0 0 0-5.834-.357c-1.993 0-3.948.119-5.834.357v1.893Z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-baseline justify-between gap-2">
                                                    <p class="text-sm font-medium text-text-primary truncate">{{ __($entry['competition_name']) }}</p>
                                                    <span class="text-xs font-heading font-bold text-text-muted shrink-0">×{{ $entry['count'] }}</span>
                                                </div>
                                                <p class="text-xs text-text-muted leading-relaxed">{{ implode(', ', $entry['seasons']) }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-section-card>
        </div>
    </div>
</x-app-layout>
