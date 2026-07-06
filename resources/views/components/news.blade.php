@props([
    'narratives' => [],
    'game',
    'limit' => 4,
])

@php
    $items = $limit ? array_slice($narratives, 0, $limit) : $narratives;
@endphp

@if(!empty($items))
<x-section-card :title="__('game.news')">
    <div class="divide-y divide-border-default">
        @foreach($items as $narrative)
            <x-news-item :narrative="$narrative" :game="$game" />
        @endforeach
    </div>
</x-section-card>
@endif
