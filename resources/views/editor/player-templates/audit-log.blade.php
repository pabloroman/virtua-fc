<x-admin-layout>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
            {{ __('admin.audit_log_title') }}
        </h1>
        <a href="{{ route('editor.player-templates.index') }}"
           class="text-sm text-accent-blue hover:underline">
            {{ __('admin.player_templates_title') }}
        </a>
    </div>

    @if($audits->isEmpty())
        <div class="bg-surface-800 border border-border-default rounded-xl p-8 text-center">
            <p class="text-sm text-text-muted">{{ __('admin.audit_empty') }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border-default bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.tpl_date') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.user') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.tpl_action') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.tpl_player') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.tpl_team') }}</th>
                        <th class="px-4 py-3 text-left text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.changed_fields') }}</th>
                        <th class="px-4 py-3 text-right text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-default">
                    @foreach($audits as $audit)
                        <tr>
                            <td class="px-4 py-3 text-xs text-text-muted whitespace-nowrap">
                                {{ $audit->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-text-primary">
                                {{ $audit->user?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $actionColors = [
                                        'created' => 'bg-accent-green/10 text-accent-green ring-accent-green/20',
                                        'updated' => 'bg-accent-blue/10 text-accent-blue ring-accent-blue/20',
                                        'deleted' => 'bg-accent-red/10 text-accent-red ring-accent-red/20',
                                        'restored' => 'bg-accent-gold/10 text-accent-gold ring-accent-gold/20',
                                    ];
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $actionColors[$audit->action] ?? '' }}">
                                    {{ __('admin.action_' . $audit->action) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-text-primary">
                                {{ $audit->template?->player?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-text-muted hidden md:table-cell">
                                {{ $audit->template?->team?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-text-muted hidden md:table-cell">
                                @if($audit->action === 'updated' && $audit->old_values && $audit->new_values)
                                    @php
                                        $changed = collect($audit->new_values)->filter(function ($val, $key) use ($audit) {
                                            return isset($audit->old_values[$key]) && $audit->old_values[$key] != $val;
                                        })->keys();
                                    @endphp
                                    {{ $changed->join(', ') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($audit->template)
                                    <a href="{{ route('editor.player-templates.squad', ['teamId' => $audit->template->team_id, 'season' => $audit->template->season]) }}"
                                       class="text-xs text-accent-blue hover:underline">
                                        {{ __('admin.edit_squad') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin-layout>
