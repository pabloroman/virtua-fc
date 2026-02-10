<x-mail::message>
# {{ __('beta.email_greeting') }}

{{ __('beta.email_body') }}

<x-mail::button :url="$registerUrl">
{{ __('beta.email_cta') }}
</x-mail::button>

{{ __('beta.email_code_label') }} **{{ $code }}**

{{ __('beta.email_warning') }}

{{ __('beta.email_thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
