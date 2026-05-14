@php
/** @var array<int, array{type_label:string, detail:string, cost_label:string, status_label:string, ready_label:string, is_completed:bool}> $historyRows */
@endphp

<x-section-card :title="__('club.stadium.history.title')">
    @if(empty($historyRows))
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
                    @foreach($historyRows as $row)
                        <tr class="border-b border-border-default">
                            <td class="px-5 py-2.5 font-semibold text-text-primary">{{ $row['type_label'] }}</td>
                            <td class="py-2.5 text-text-secondary hidden md:table-cell">{{ $row['detail'] }}</td>
                            <td class="py-2.5 pl-4 text-right font-heading font-semibold text-base text-text-body whitespace-nowrap">{{ $row['cost_label'] }}</td>
                            <td class="py-2.5 pl-4 pr-5 text-right">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $row['is_completed'] ? 'bg-accent-green/10 text-accent-green' : 'bg-accent-gold/10 text-accent-gold' }}">
                                    {{ $row['status_label'] }}
                                </span>
                                <div class="text-[11px] text-text-faint mt-0.5">{{ $row['ready_label'] }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-section-card>
