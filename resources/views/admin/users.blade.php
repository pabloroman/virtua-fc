<x-admin-layout>
    <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-6">
        {{ __('admin.users_title') }}
    </h1>

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
                                <span class="ml-1 inline-flex items-center rounded-full bg-purple-500/10 px-2 py-0.5 text-xs font-medium text-purple-500 ring-1 ring-inset ring-purple-500/20">Admin</span>
                            @endif
                            @if($user->has_career_access)
                                <span class="ml-1 inline-flex items-center rounded-full bg-accent-green/10 px-2 py-0.5 text-xs font-medium text-accent-green ring-1 ring-inset ring-accent-green/20">{{ __('admin.career_access') }}</span>
                            @endif
                            @if($user->can_edit_database)
                                <span class="ml-1 inline-flex items-center rounded-full bg-accent-blue/10 px-2 py-0.5 text-xs font-medium text-accent-blue ring-1 ring-inset ring-accent-blue/20">{{ __('admin.database_editing') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-text-muted hidden md:table-cell">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-sm text-text-muted">{{ $user->games_count }}</td>
                        <td class="px-4 py-3 text-sm text-text-muted hidden md:table-cell">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.toggle-career', $user->id) }}" class="inline">
                                    @csrf
                                    @if($user->has_career_access)
                                        <x-ghost-button type="submit" color="red" size="xs">
                                            {{ __('admin.revoke_career') }}
                                        </x-ghost-button>
                                    @else
                                        <x-ghost-button type="submit" color="green" size="xs">
                                            {{ __('admin.grant_career') }}
                                        </x-ghost-button>
                                    @endif
                                </form>
                                <form method="POST" action="{{ route('admin.toggle-database-editing', $user->id) }}" class="inline">
                                    @csrf
                                    @if($user->can_edit_database)
                                        <x-ghost-button type="submit" color="red" size="xs">
                                            {{ __('admin.revoke_database_editing') }}
                                        </x-ghost-button>
                                    @else
                                        <x-ghost-button type="submit" color="blue" size="xs">
                                            {{ __('admin.grant_database_editing') }}
                                        </x-ghost-button>
                                    @endif
                                </form>
                                <form method="POST" action="{{ route('admin.impersonate', $user->id) }}" class="inline">
                                    @csrf
                                    <x-ghost-button type="submit" color="blue" size="xs">
                                        {{ __('admin.impersonate') }}
                                    </x-ghost-button>
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
</x-admin-layout>
