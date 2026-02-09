<x-mail::message>
# {{ __('beta.feedback_email_greeting', ['name' => $userName]) }}

{{ __('beta.feedback_email_body') }}

{{ __('beta.feedback_email_questions') }}

<x-mail::button :url="$feedbackUrl">
{{ __('beta.feedback_email_cta') }}
</x-mail::button>

{{ __('beta.feedback_email_reply_hint') }}

{{ __('beta.feedback_email_thanks') }}<br>
Pablo — {{ config('app.name') }}
</x-mail::message>
