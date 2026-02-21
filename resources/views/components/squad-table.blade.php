@props(['sticky' => false, 'tbodyRef' => null])

<div class="overflow-x-auto">
    <table class="w-full table-fixed text-sm">
        <thead class="text-left border-b border-slate-300">
            @if(isset($headRow))
                {{ $headRow }}
            @else
            <tr>
                <th class="font-semibold py-2 w-10 {{ $sticky ? 'sticky left-0 bg-white z-10' : '' }}"></th>
                <th class="font-semibold py-2 text-center w-8 text-slate-400 hidden md:table-cell">#</th>
                <th class="font-semibold py-2 w-1/2 {{ $sticky ? 'sticky left-10 bg-white z-10' : '' }}">{{ __('app.name') }}</th>
                <th class="py-2 w-6"></th>
                <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.country') }}</th>
                <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.age') }}</th>
                <th class="py-2"></th>
                {{ $extraHeaders ?? '' }}
            </tr>
            @endif
        </thead>
        <tbody @if($tbodyRef) x-ref="{{ $tbodyRef }}" @endif>
            {{ $slot }}
        </tbody>
    </table>
</div>
