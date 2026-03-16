<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text-primary leading-tight">
            {{ __('profile.title') }}
        </h2>
    </x-slot>

    <div class="py-6 md:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Left column: Profile Information --}}
                <div class="lg:col-span-2">
                    <x-section-card :title="__('profile.profile_information')">
                        @include('profile.partials.update-profile-information-form')
                    </x-section-card>
                </div>

                {{-- Right column: Password & Delete --}}
                <div class="space-y-6">
                    <x-section-card :title="__('profile.change_password')">
                        @include('profile.partials.update-password-form')
                    </x-section-card>

                    <x-section-card :title="__('profile.delete_account')">
                        @include('profile.partials.delete-user-form')
                    </x-section-card>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
