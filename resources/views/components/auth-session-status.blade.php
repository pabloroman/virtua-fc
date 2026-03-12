@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-accent-green']) }}>
        {{ $status }}
    </div>
@endif
