<x-admin-layout>
    <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-6">
        {{ __('admin.waitlist_title') }}
    </h1>

    <x-flash-message type="success" :message="session('success')" class="mb-4" />
    <x-flash-message type="error" :message="session('error')" class="mb-4" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.waitlist_total') }}</div>
            <div class="font-heading text-2xl font-bold text-text-primary">{{ number_format($total) }}</div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.waitlist_pending') }}</div>
            <div class="font-heading text-2xl font-bold text-accent-gold">{{ number_format($pending) }}</div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.waitlist_invited') }}</div>
            <div class="font-heading text-2xl font-bold text-accent-blue">{{ number_format($invited) }}</div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.waitlist_registered') }}</div>
            <div class="font-heading text-2xl font-bold text-accent-green">{{ number_format($registered) }}</div>
        </div>
    </div>

    @if($pending > 0 && config('beta.enabled'))
        <form method="POST" action="{{ route('admin.bulk-waitlist-invite') }}" class="mb-4">
            @csrf
            <x-ghost-button
                type="submit"
                color="green"
                size="xs"
                x-on:click.prevent="if (confirm(@js(__('admin.waitlist_bulk_invite_confirm')))) $el.closest('form').submit()"
            >
                {{ __('admin.waitlist_bulk_invite') }}
            </x-ghost-button>
        </form>
    @endif

    <form method="GET" action="{{ route('admin.waitlist') }}" class="mb-4">
        <div class="relative max-w-xs">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-text-muted pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
                type="text"
                name="search"
                value="{{ $search ?? '' }}"
                placeholder="{{ __('admin.search_waitlist_placeholder') }}"
                class="w-full bg-surface-700 border border-border-default rounded-md text-xs text-text-primary placeholder-slate-500 pl-8 pr-3 py-1.5 focus:outline-hidden focus:border-accent-blue/50 min-h-[44px]"
            />
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border-default">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.user') }}</th>
                    <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.email') }}</th>
                    <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.waitlist_mode') }}</th>
                    <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.waitlist_status') }}</th>
                    <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.waitlist_signed_up') }}</th>
                    <th class="px-4 py-3 text-right text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border-default">
                @foreach($entries as $entry)
                    <tr>
                        <td class="px-4 py-3 text-sm text-text-primary">
                            {{ $entry->name }}
                            <div class="text-xs text-text-muted md:hidden">{{ $entry->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-text-muted hidden md:table-cell">{{ $entry->email }}</td>
                        <td class="px-4 py-3 text-sm hidden md:table-cell">
                            <div class="flex gap-1">
                                @if($entry->wants_career)
                                    <span class="inline-flex items-center rounded-full bg-accent-blue/10 px-2 py-0.5 text-[10px] font-medium text-accent-blue ring-1 ring-inset ring-accent-blue/20">
                                        {{ __('admin.waitlist_mode_career') }}
                                    </span>
                                @endif
                                @if($entry->wants_tournament)
                                    <span class="inline-flex items-center rounded-full bg-accent-green/10 px-2 py-0.5 text-[10px] font-medium text-accent-green ring-1 ring-inset ring-accent-green/20">
                                        {{ __('admin.waitlist_mode_tournament') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if(! $entry->inviteCode)
                                <span class="inline-flex items-center rounded-full bg-accent-gold/10 px-2 py-0.5 text-xs font-medium text-accent-gold ring-1 ring-inset ring-accent-gold/20">
                                    {{ __('admin.waitlist_status_pending') }}
                                </span>
                            @elseif($entry->inviteCode->times_used > 0)
                                <span class="inline-flex items-center rounded-full bg-accent-green/10 px-2 py-0.5 text-xs font-medium text-accent-green ring-1 ring-inset ring-accent-green/20">
                                    {{ __('admin.waitlist_status_registered') }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-accent-blue/10 px-2 py-0.5 text-xs font-medium text-accent-blue ring-1 ring-inset ring-accent-blue/20">
                                    {{ __('admin.waitlist_status_invited') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-text-muted hidden md:table-cell">{{ $entry->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if(! $entry->inviteCode)
                                <form method="POST" action="{{ route('admin.send-waitlist-invite', $entry) }}" class="inline">
                                    @csrf
                                    <x-ghost-button type="submit" color="blue" size="xs">
                                        {{ __('admin.waitlist_send_invite') }}
                                    </x-ghost-button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @if($entries->isEmpty())
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-text-muted">
                            {{ __('admin.no_results') }}
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $entries->links() }}
    </div>
</x-admin-layout>
