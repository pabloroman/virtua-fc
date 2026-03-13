@props(['modalName'])

<div class="flex items-center justify-between px-5 py-4 border-b border-border-default">
    <h3 class="font-heading text-lg font-semibold text-text-primary">{{ $slot }}</h3>
    <x-icon-button size="sm" @click="$dispatch('close-modal', '{{ $modalName }}')">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </x-icon-button>
</div>
