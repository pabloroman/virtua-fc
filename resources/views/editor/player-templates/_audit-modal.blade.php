<x-modal name="audit-{{ $template->id }}" maxWidth="2xl">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading text-lg font-bold uppercase tracking-wide text-text-primary">
                {{ __('admin.history') }} — {{ $template->player?->name }}
            </h2>
            <button @click="$dispatch('close-modal', 'audit-{{ $template->id }}')" class="text-text-muted hover:text-text-secondary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        @if($template->audits->isEmpty())
            <p class="text-sm text-text-muted">{{ __('admin.no_history') }}</p>
        @else
            <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                @foreach($template->audits as $audit)
                    <div class="bg-surface-700/50 rounded-lg p-3 border border-border-default">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
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
                                <span class="text-xs text-text-muted">{{ $audit->user?->name ?? '—' }}</span>
                            </div>
                            <span class="text-xs text-text-muted">{{ $audit->created_at->format('d/m/Y H:i') }}</span>
                        </div>

                        {{-- Changed fields diff --}}
                        @if($audit->action === 'updated' && $audit->old_values && $audit->new_values)
                            <div class="text-xs space-y-1">
                                @foreach($audit->new_values as $key => $newVal)
                                    @if(isset($audit->old_values[$key]) && $audit->old_values[$key] != $newVal)
                                        <div class="flex items-center gap-2">
                                            <span class="text-text-muted font-medium w-32 shrink-0">{{ $key }}</span>
                                            <span class="text-accent-red line-through">{{ $audit->old_values[$key] ?? '—' }}</span>
                                            <svg class="w-3 h-3 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                                            <span class="text-accent-green">{{ $newVal ?? '—' }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @elseif($audit->action === 'created')
                            <div class="text-xs text-text-muted">{{ __('admin.action_created') }}</div>
                        @endif

                        {{-- Restore button --}}
                        @if($audit->action !== 'deleted' && $audit->new_values)
                            <div class="mt-2 pt-2 border-t border-border-default">
                                <form method="POST"
                                      action="{{ route('editor.player-templates.restore', [$template->id, $audit->id]) }}"
                                      onsubmit="return confirm('{{ __('admin.restore_confirm') }}')">
                                    @csrf
                                    <x-ghost-button type="submit" color="amber" size="xs">
                                        {{ __('admin.restore') }}
                                    </x-ghost-button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-modal>
