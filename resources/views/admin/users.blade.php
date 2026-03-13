<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary leading-tight text-center">
            {{ __('admin.users_title') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('admin.users_title') }}</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border-default">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.user') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.email') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.games') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.registered') }}</th>
                        <th class="px-4 py-3 text-right text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @foreach($users as $user)
                        <tr>
                            <td class="px-4 py-3 text-sm text-text-primary">
                                {{ $user->name }}
                                @if($user->is_admin)
                                    <span class="ml-1 inline-flex items-center rounded-full bg-purple-500/10 px-2 py-0.5 text-xs font-medium text-purple-400 ring-1 ring-inset ring-purple-700/10">Admin</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-text-muted hidden md:table-cell">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-sm text-text-muted">{{ $user->games_count }}</td>
                            <td class="px-4 py-3 text-sm text-text-muted hidden md:table-cell">{{ $user->created_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.impersonate', $user->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-sm font-medium text-accent-blue hover:text-accent-blue min-h-[44px]">
                                            {{ __('admin.impersonate') }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-sm text-text-secondary">{{ __('admin.current_user') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
