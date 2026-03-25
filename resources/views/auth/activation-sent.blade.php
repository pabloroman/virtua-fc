<x-guest-layout>
    <div class="text-center">
        <div class="mb-4">
            <svg class="mx-auto h-12 w-12 text-accent-green" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </svg>
        </div>

        <h2 class="text-lg font-semibold text-text-primary">
            {{ __('auth.activation_sent_title') }}
        </h2>

        <p class="mt-2 text-sm text-text-secondary">
            {{ __('auth.activation_sent_body') }}
        </p>

        <p class="mt-4 text-xs text-text-tertiary">
            {{ __('auth.activation_sent_expiry') }}
        </p>

        <div class="mt-6">
            <p class="text-sm text-text-secondary">
                {{ __('auth.activation_sent_no_email') }}
            </p>
            <a href="{{ route('password.request') }}" class="mt-1 inline-block text-sm text-accent-blue hover:underline">
                {{ __('auth.Resend Activation Email') }}
            </a>
        </div>
    </div>
</x-guest-layout>
