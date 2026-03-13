@props([
    'standing',
    'isPlayer' => false,
    'showGap' => false,
])

@if($showGap)
<div class="px-4 py-0.5 text-center text-text-faint text-[10px]">&middot;&middot;&middot;</div>
@endif

<div class="grid grid-cols-[24px_1fr_28px_28px_28px_32px_36px] gap-1 px-4 items-center {{ $isPlayer ? 'py-2.5 bg-accent-blue/[0.06] border-l-2 border-l-accent-blue' : 'py-2' }}">
    <span class="text-[11px] font-heading font-semibold {{ $isPlayer ? 'text-accent-blue' : 'text-text-muted' }}">{{ $standing->position }}</span>
    <div class="flex items-center gap-2 min-w-0">
        <x-team-crest :team="$standing->team" class="w-5 h-5 shrink-0" />
        <span class="text-xs truncate {{ $isPlayer ? 'text-text-primary font-semibold' : 'text-text-body' }}">{{ $standing->team->name }}</span>
    </div>
    <span class="text-[11px] text-center {{ $isPlayer ? 'text-text-primary font-medium' : 'text-text-muted' }}">{{ $standing->won }}</span>
    <span class="text-[11px] text-center {{ $isPlayer ? 'text-text-primary font-medium' : 'text-text-muted' }}">{{ $standing->drawn }}</span>
    <span class="text-[11px] text-center {{ $isPlayer ? 'text-text-primary font-medium' : 'text-text-muted' }}">{{ $standing->lost }}</span>
    <span class="text-[11px] text-center {{ $isPlayer ? 'text-text-primary font-medium' : 'text-text-muted' }}">{{ $standing->goal_difference >= 0 ? '+' : '' }}{{ $standing->goal_difference }}</span>
    <span class="text-[11px] text-right font-semibold {{ $isPlayer ? 'text-accent-blue font-bold' : 'text-text-primary' }}">{{ $standing->points }}</span>
</div>
