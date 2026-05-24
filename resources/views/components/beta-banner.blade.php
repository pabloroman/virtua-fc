@props([
    'message' => null,
])

@if(config('beta.enabled'))
    <div class="bg-amber-500 text-amber-950 text-center text-xs py-1.5 px-4">
        <span class="font-semibold">{{ __('beta.badge') }}</span>
        —
        {{ $message ?? __('beta.banner_warning') }}
        @if(config('beta.feedback_url'))
            · <a href="{{ config('beta.feedback_url') }}" target="_blank" class="underline font-semibold hover:text-amber-300">{{ __('beta.send_feedback') }}</a>
        @endif
    </div>
@endif
