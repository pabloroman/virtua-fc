@php
/**
 * @var App\Models\Game $game
 * @var \Illuminate\Database\Eloquent\Collection<int, App\Models\GameStadiumProject> $projectHistory
 */

use App\Models\GameStadiumProject;
@endphp

<x-section-card :title="__('club.stadium.history.title')">
    @if($projectHistory->isEmpty())
        <div class="px-5 py-8 text-center">
            <p class="text-sm text-text-muted">{{ __('club.stadium.history.empty') }}</p>
            <p class="text-xs text-text-faint mt-1">{{ __('club.stadium.history.empty_hint') }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] text-text-muted uppercase tracking-widest border-b border-border-default">
                        <th class="px-5 py-2.5 font-semibold">{{ __('club.stadium.history.col_type') }}</th>
                        <th class="py-2.5 font-semibold hidden md:table-cell">{{ __('club.stadium.history.col_detail') }}</th>
                        <th class="py-2.5 pl-4 font-semibold text-right">{{ __('club.stadium.history.col_cost') }}</th>
                        <th class="py-2.5 pl-4 pr-5 font-semibold text-right">{{ __('club.stadium.history.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($projectHistory as $project)
                        @php
                            // Additive projects (supplementary, stand
                            // expansion) display as "+N"; rebuild's
                            // target_capacity is a total, not a delta.
                            $detail = match ($project->type) {
                                GameStadiumProject::TYPE_SUPPLEMENTARY,
                                GameStadiumProject::TYPE_STAND_EXPANSION
                                    => '+'.number_format($project->target_capacity),
                                GameStadiumProject::TYPE_REBUILD
                                    => __('club.stadium.history.detail_rebuild', ['count' => number_format($project->target_capacity)]),
                                default => '',
                            };

                            $isCompleted = $project->status === GameStadiumProject::STATUS_COMPLETED;

                            // "When is it ready" label varies by project shape:
                            // supplementary lands on a calendar date, the
                            // others land at the start of a season.
                            $readyLabel = $project->completion_date
                                ? $project->completion_date->isoFormat('LL')
                                : ($project->completion_season
                                    ? __('club.stadium.history.season_label', ['season' => $project->completion_season])
                                    : '—');
                        @endphp
                        <tr class="border-b border-border-default">
                            <td class="px-5 py-2.5 font-semibold text-text-primary">
                                {{ __('club.stadium.upgrades.project_'.$project->type) }}
                            </td>
                            <td class="py-2.5 text-text-secondary hidden md:table-cell">
                                {{ $detail }}
                            </td>
                            <td class="py-2.5 pl-4 text-right font-heading font-semibold text-base text-text-body whitespace-nowrap">
                                {{ $project->formatted_total_cost }}
                            </td>
                            <td class="py-2.5 pl-4 pr-5 text-right">
                                @if($isCompleted)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-accent-green/10 text-accent-green">
                                        {{ __('club.stadium.history.status_completed') }}
                                    </span>
                                    <div class="text-[11px] text-text-faint mt-0.5">{{ $readyLabel }}</div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-accent-gold/10 text-accent-gold">
                                        {{ __('club.stadium.history.status_in_progress') }}
                                    </span>
                                    <div class="text-[11px] text-text-faint mt-0.5">{{ __('club.stadium.history.ready_label', ['date' => $readyLabel]) }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-section-card>
