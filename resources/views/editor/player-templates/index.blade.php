<x-admin-layout>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
            {{ __('admin.player_templates_title') }}
        </h1>
        <a href="{{ route('editor.player-templates.audit-log') }}"
           class="text-sm text-accent-blue hover:underline">
            {{ __('admin.audit_log') }}
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('editor.player-templates.index') }}"
          class="bg-surface-800 border border-border-default rounded-xl p-4 mb-6">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <x-text-input
                    type="text"
                    name="search"
                    :value="$search"
                    placeholder="{{ __('admin.search_team') }}"
                    class="w-full"
                />
            </div>
            <div class="w-full sm:w-40">
                <x-select-input name="season" class="w-full">
                    @foreach($seasons as $season)
                        <option value="{{ $season }}" {{ $selectedSeason === $season ? 'selected' : '' }}>
                            {{ $season }}
                        </option>
                    @endforeach
                </x-select-input>
            </div>
            <x-primary-button type="submit" color="blue" size="xs">
                {{ __('admin.filter') }}
            </x-primary-button>
        </div>
    </form>

    {{-- Team Grid --}}
    @if($teams->isEmpty())
        <div class="bg-surface-800 border border-border-default rounded-xl p-8 text-center">
            <p class="text-sm text-text-muted">{{ __('admin.no_data') }}</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($teams as $team)
                <a href="{{ route('editor.player-templates.squad', ['teamId' => $team->id, 'season' => $selectedSeason]) }}"
                   class="bg-surface-800 border border-border-default rounded-xl p-4 hover:bg-surface-700 transition-colors flex items-center gap-4 min-h-[44px]">
                    @if($team->image)
                        <img src="{{ $team->image }}" alt="" class="w-8 h-8 shrink-0">
                    @else
                        <div class="w-8 h-8 shrink-0 rounded bg-surface-700"></div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-text-primary truncate">{{ $team->name }}</div>
                        <div class="text-xs text-text-muted">{{ $team->country }}</div>
                    </div>
                    <div class="text-xs text-text-muted shrink-0">
                        {{ $team->players_count }} {{ __('admin.players_count') }}
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-admin-layout>
