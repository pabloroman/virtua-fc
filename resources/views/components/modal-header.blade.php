@props([
    'modalName' => null,   // when set, renders a close (X) button that dispatches close-modal; omit for must-act headers
    'tone' => 'default',   // 'default' | 'danger' | 'success' | 'info' — tints the optional icon chip and eyebrow label
    'eyebrow' => false,    // render the title as a small uppercase label instead of the standard heading
])

@php
    // Tone tints the optional icon chip and the eyebrow label; the standard
    // heading title always stays neutral (text-text-primary).
    $iconChip = match ($tone) {
        'danger' => 'bg-red-600/15 text-red-500',
        'success' => 'bg-emerald-500/15 text-emerald-500',
        'info' => 'bg-accent-blue/15 text-accent-blue',
        default => 'bg-surface-700 text-text-secondary',
    };
    $eyebrowColor = match ($tone) {
        'danger' => 'text-red-500',
        'success' => 'text-emerald-500',
        'info' => 'text-accent-blue',
        default => 'text-text-primary',
    };
@endphp

<div class="flex items-center gap-3 px-5 py-4 border-b border-border-default">
    <div class="flex items-center gap-3 min-w-0 flex-1">
        @isset($icon)
        <span class="flex h-8 w-8 items-center justify-center rounded-full shrink-0 {{ $iconChip }}">
            {{ $icon }}
        </span>
        @endisset

        @if($eyebrow)
        <p class="text-xs font-semibold uppercase tracking-wide {{ $eyebrowColor }}">{{ $slot }}</p>
        @else
        <h3 class="font-heading text-lg font-semibold text-text-primary truncate">{{ $slot }}</h3>
        @endif
    </div>

    @if($modalName)
    <x-icon-button size="sm" @click="$dispatch('close-modal', '{{ $modalName }}')">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </x-icon-button>
    @endif
</div>
