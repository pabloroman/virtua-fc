<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary leading-tight text-center">
            {{ __('admin.activation_title') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        {{-- Header with title and period filter --}}
        <div class="mt-6 mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('admin.activation_title') }}</h2>

            <div class="flex items-center gap-2">
                @foreach(['7' => '7d', '30' => '30d', '90' => '90d', 'all' => __('admin.all_time')] as $value => $label)
                    <a href="{{ route('admin.activation', ['period' => $value]) }}"
                       class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors min-h-[44px] flex items-center
                              {{ $period === (string) $value ? 'bg-accent-primary text-white' : 'bg-surface-700 text-text-secondary hover:text-text-primary' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-surface-700/30 border border-border-default rounded-xl p-4">
                <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.invites_sent') }}</div>
                <div class="text-2xl font-bold text-text-primary">{{ number_format($totalInvites) }}</div>
            </div>
            <div class="bg-surface-700/30 border border-border-default rounded-xl p-4">
                <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.users_registered') }}</div>
                <div class="text-2xl font-bold text-text-primary">{{ number_format($totalRegistered) }}</div>
            </div>
            <div class="bg-surface-700/30 border border-border-default rounded-xl p-4">
                <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.conversion_to_first_match') }}</div>
                <div class="text-2xl font-bold text-accent-primary">{{ $overallConversion }}%</div>
            </div>
        </div>

        {{-- Funnel visualization --}}
        <div class="bg-surface-700/30 border border-border-default rounded-xl p-4 md:p-6">
            <h3 class="text-sm font-semibold text-text-primary uppercase tracking-wider mb-6">{{ __('admin.funnel_steps') }}</h3>

            <div class="space-y-3">
                @foreach($steps as $i => $step)
                    <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
                        {{-- Step label --}}
                        <div class="md:w-48 shrink-0">
                            <span class="text-xs text-text-muted">{{ $i }}.</span>
                            <span class="text-sm text-text-primary">{{ $step['label'] }}</span>
                        </div>

                        {{-- Bar --}}
                        <div class="flex-1 flex items-center gap-3">
                            <div class="flex-1 bg-surface-600 rounded-full h-6 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500
                                            {{ $step['percentage'] >= 60 ? 'bg-emerald-500/70' : ($step['percentage'] >= 30 ? 'bg-amber-500/70' : 'bg-rose-500/70') }}"
                                     style="width: {{ max($step['percentage'], 1) }}%">
                                </div>
                            </div>

                            {{-- Count --}}
                            <div class="w-16 text-right">
                                <span class="text-sm font-semibold text-text-primary">{{ number_format($step['count']) }}</span>
                            </div>

                            {{-- Percentage of top --}}
                            <div class="w-14 text-right hidden md:block">
                                <span class="text-xs text-text-muted">{{ $step['percentage'] }}%</span>
                            </div>

                            {{-- Drop-off --}}
                            <div class="w-20 text-right hidden md:block">
                                @if($i > 0 && $step['drop_off'] > 0)
                                    <span class="text-xs font-medium {{ $step['drop_off'] >= 50 ? 'text-rose-400' : ($step['drop_off'] >= 25 ? 'text-amber-400' : 'text-text-muted') }}">
                                        -{{ $step['drop_off'] }}%
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Mobile drop-off table (visible only on mobile where inline drop-off is hidden) --}}
        <div class="mt-6 md:hidden bg-surface-700/30 border border-border-default rounded-xl p-4">
            <h3 class="text-sm font-semibold text-text-primary uppercase tracking-wider mb-4">{{ __('admin.drop_off_rates') }}</h3>
            <div class="space-y-2">
                @foreach($steps as $i => $step)
                    @if($i > 0 && $step['drop_off'] > 0)
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-text-secondary truncate">{{ $step['label'] }}</span>
                            <span class="text-xs font-medium {{ $step['drop_off'] >= 50 ? 'text-rose-400' : ($step['drop_off'] >= 25 ? 'text-amber-400' : 'text-text-muted') }}">
                                -{{ $step['drop_off'] }}%
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
