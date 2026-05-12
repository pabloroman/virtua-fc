@props(['tips' => [], 'emptyMessage' => null])

@php
    /**
     * Coach-tip / recommendation list with colored dot bullets.
     *
     * $tips: iterable of ['type' => 'info'|'warning'|'danger', 'message' => string].
     * $emptyMessage: optional copy shown when the list is empty.
     *
     * Caller decides outer padding — typically wrapped in a section-card body
     * with `p-4` (see /opponent recommendations and the squad planner sidebar).
     */
@endphp

@if(empty($tips))
    @if($emptyMessage)
        <p class="text-xs text-text-secondary italic">{{ $emptyMessage }}</p>
    @endif
@else
    <div class="space-y-2">
        @foreach($tips as $tip)
            @php
                $type = is_array($tip) ? ($tip['type'] ?? 'info') : ($tip->type ?? 'info');
                $message = is_array($tip) ? ($tip['message'] ?? '') : ($tip->message ?? '');
                $dotClass = match($type) {
                    'warning' => 'bg-amber-400',
                    'danger' => 'bg-red-400',
                    default => 'bg-sky-400',
                };
            @endphp
            <div class="flex items-start gap-2">
                <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0 {{ $dotClass }}"></span>
                <span class="text-xs text-text-secondary leading-relaxed">{{ $message }}</span>
            </div>
        @endforeach
    </div>
@endif
