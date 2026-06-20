@props(['notifications'])

{{-- Department filter tabs for the notifications inbox. Drives an Alpine `dept`
     property defined on a surrounding x-data scope (default 'all'); each tab
     sets it and the inbox rows filter client-side via x-show. Only departments
     actually present in the list get a tab, so selecting one always has results.
     Renders nothing when a single department is present (a filter adds nothing). --}}
@php
    $summary = \App\Models\GameNotification::departmentSummary($notifications);
@endphp

@if(count($summary) > 1)
<div class="flex items-center gap-1 overflow-x-auto px-3 py-2 border-b border-border-default">
    <button type="button"
        @click="dept = 'all'"
        :class="dept === 'all' ? 'bg-accent-blue/10 text-accent-blue' : 'text-text-muted hover:text-text-secondary hover:bg-surface-700'"
        class="shrink-0 px-2.5 py-1 rounded-full text-[11px] font-medium transition-colors whitespace-nowrap">
        {{ __('notifications.dept_all') }}
    </button>
    @foreach($summary as $department)
    <button type="button"
        @click="dept = @js($department['key'])"
        :class="dept === @js($department['key']) ? 'bg-accent-blue/10 text-accent-blue' : 'text-text-muted hover:text-text-secondary hover:bg-surface-700'"
        class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium transition-colors whitespace-nowrap">
        {{ __('notifications.dept_' . $department['key']) }}
        @if($department['unread'] > 0)
        <span class="inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-accent-blue text-white text-[9px] font-bold leading-none">{{ $department['unread'] }}</span>
        @endif
    </button>
    @endforeach
</div>
@endif
