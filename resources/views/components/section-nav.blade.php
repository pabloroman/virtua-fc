@props(['items'])

<div class="flex border-b border-slate-200 mb-0 overflow-x-auto scrollbar-hide">
    @foreach($items as $item)
        <a href="{{ $item['href'] }}"
           class="shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $item['active'] ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
            {{ $item['label'] }}
            @if(!empty($item['badge']))
                <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold bg-red-600 text-white rounded-full">{{ $item['badge'] }}</span>
            @endif
        </a>
    @endforeach
</div>
