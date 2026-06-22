@props(['game', 'player', 'statusChips' => []])

@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GamePlayer $player */
    /** @var array<int, array{text: string, class: string}> $statusChips */

    $isCareerMode = $game->isCareerMode();
    $nationalityFlag = $player->nationality_flag;
    $defaultPhoto = Storage::disk('assets')->url('img/default-player.jpg');
    $photo = $player->image_url ?? $defaultPhoto;
    $overall = $player->effective_rating;
    $overallColor = match (true) {
        $overall >= 80 => 'bg-accent-green',
        $overall >= 70 => 'bg-lime-500',
        $overall >= 60 => 'bg-accent-gold',
        default => 'bg-surface-600',
    };
@endphp

{{-- Shared player banner: avatar + identity + overall. Used by the owned-player
     detail (server-rendered) and the scouting dossier (pre-rendered into its
     payload). Status chips differ per surface, so the caller supplies them. --}}
<div class="px-5 py-4 bg-surface-900/50 border-b border-border-default">
    <div class="flex items-center gap-4">
        {{-- Avatar --}}
        <div class="relative shrink-0">
            {{-- Falls back to the default avatar when the CDN has no photo for this
                 player (no Sofascore ID, or the .webp 404s). The onerror handler
                 survives the dossier modal's x-html injection. --}}
            <img src="{{ $photo }}"
                 @if($photo !== $defaultPhoto) onerror="this.onerror=null;this.src='{{ $defaultPhoto }}'" @endif
                 class="h-20 w-auto md:h-24 rounded-lg border border-border-default bg-surface-700" alt="">
        </div>

        {{-- Info --}}
        <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-muted">
                @if($nationalityFlag)
                    <span class="inline-flex items-center gap-1.5">
                        <img src="{{ Storage::disk('assets')->url('flags/' . $nationalityFlag['code'] . '.svg') }}" class="w-4 h-3 rounded-xs shadow-xs">
                        {{ __('countries.' . $nationalityFlag['name']) }}
                    </span>
                @endif
                @if($player->team && $isCareerMode)
                    <span class="inline-flex items-center gap-1.5 min-w-0">
                        <x-team-crest :team="$player->team" class="w-4 h-4 shrink-0" />
                        <span class="truncate">{{ $player->team->name }}</span>
                    </span>
                @endif
                <span>{{ $player->age($game->current_date) }} {{ __('app.years') }}@if($player->height) · {{ $player->height }}@endif</span>
            </div>
            <div class="text-[11px] text-text-faint mt-1">
                @foreach($player->positions as $pos)
                    <span class="text-text-secondary">{{ \App\Support\PositionMapper::toDisplayName($pos) }}</span>@if(!$loop->last)<span class="text-text-faint/60"> · </span>@endif
                @endforeach
            </div>

            {{-- Status badges (surface-specific, supplied by the caller) --}}
            @if(!empty($statusChips))
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    @foreach($statusChips as $chip)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $chip['class'] }}">{{ $chip['text'] }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Overall score --}}
        <div class="w-14 h-14 md:w-16 md:h-16 rounded-xl {{ $overallColor }} flex items-center justify-center shrink-0">
            <span class="text-xl md:text-2xl font-bold text-white">{{ $overall }}</span>
        </div>
    </div>
</div>
