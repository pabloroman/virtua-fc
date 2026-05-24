<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl md:text-3xl font-bold text-text-primary">{{ __('live_duel.title') }}</h1>
        <p class="text-text-muted mt-1">
            @if ($role === 'host')
                {{ __('live_duel.host_pick_prompt') }}
            @else
                {{ __('live_duel.guest_pick_prompt', ['host' => $session->host->name ?? '']) }}
            @endif
        </p>
    </x-slot>

    <div class="max-w-5xl mx-auto p-4 md:p-6">
        @if ($errors->any())
            <div class="mb-4 p-4 bg-accent-red/10 border border-accent-red/30 rounded-lg text-text-primary">
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ $role === 'host' ? route('live.duel.create') : route('live.duel.pick-team', ['session' => $session->id]) }}"
            class="space-y-4"
        >
            @csrf
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach ($teams as $team)
                    @php
                        $count = $eligibility[$team->id] ?? 0;
                        $taken = $takenTeamId === $team->id;
                        $disabled = $taken || $count < \App\Modules\LiveMatch\Services\NationalSquadBuilder::MIN_FOR_VIABLE_SQUAD;
                    @endphp
                    <button
                        type="submit"
                        name="team_id"
                        value="{{ $team->id }}"
                        @if ($disabled) disabled @endif
                        class="group flex flex-col items-center justify-center gap-2 p-4 rounded-xl border transition-all
                            {{ $disabled
                                ? 'bg-surface-800 border-border-default opacity-50 cursor-not-allowed'
                                : 'bg-surface-700 border-border-default hover:border-accent-blue hover:bg-accent-blue/10 cursor-pointer' }}"
                    >
                        @if ($team->image)
                            <img src="{{ $team->image }}" alt="{{ $team->name }}" class="w-12 h-12 object-contain" />
                        @else
                            <div class="text-3xl">⚽</div>
                        @endif
                        <div class="font-semibold text-text-primary">{{ $team->name }}</div>
                        @if ($taken)
                            <div class="text-xs text-text-muted">{{ __('live_duel.taken') }}</div>
                        @endif
                    </button>
                @endforeach
            </div>
        </form>
    </div>
</x-app-layout>
