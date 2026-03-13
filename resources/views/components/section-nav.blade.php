@props(['items'])

<div class="flex items-end border-b border-border-strong mb-0">
    <div class="flex overflow-x-auto scrollbar-hide">
        @foreach($items as $item)
            <a href="{{ $item['href'] }}"
               class="shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $item['active'] ? 'border-accent-blue text-text-primary' : 'border-transparent text-text-muted hover:text-text-body hover:border-border-strong' }}">
                {{ $item['label'] }}
                @if(!empty($item['badge']))
                    <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold bg-accent-red text-white rounded-full">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
    @if($slot->isNotEmpty())
        <div class="ml-auto shrink-0 pb-2 pl-3">
            {{ $slot }}
        </div>
    @endif
</div>
