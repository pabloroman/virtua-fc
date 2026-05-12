{{--
    Half-time advisor panel.

    Reads `halfTimeTips` and `halfTimeTipsDismissed` from the live-match Alpine
    component. Each tip's "Apply" button stages the recommended tactical change
    via existing pending-state slots and submits through the standard
    /tactical-actions pipeline (confirmAllChanges in tactical-submission.js) —
    no new endpoints, no new write paths.
--}}
<div class="px-3 pb-3" x-cloak>
    <template x-if="halfTimeTips && halfTimeTips.length > 0">
        <div class="rounded-xl border border-border-default bg-surface-800 overflow-hidden">
            <div class="px-3 py-2 border-b border-border-default flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full bg-accent-gold"></span>
                <span class="text-[10px] font-semibold uppercase tracking-widest text-text-secondary">
                    {{ __('coaching.advisor_title') }}
                </span>
            </div>
            <ul class="divide-y divide-border-default">
                <template x-for="tip in halfTimeTips.filter(t => !halfTimeTipsDismissed.includes(t.id))" :key="tip.id">
                    <li class="px-3 py-2.5">
                        <div class="flex items-start gap-2.5">
                            <span
                                class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0"
                                :class="{
                                    'bg-amber-400': tip.tone === 'warning',
                                    'bg-accent-green': tip.tone === 'opportunity',
                                    'bg-sky-400': tip.tone === 'info',
                                }"
                            ></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-text-body leading-snug" x-text="tip.headline"></p>
                                <p class="text-[11px] text-text-secondary leading-relaxed mt-0.5" x-text="tip.rationale"></p>
                                <div class="flex items-center gap-2 mt-2">
                                    <template x-if="tip.tacticalChange">
                                        <button
                                            type="button"
                                            @click="applyHalfTimeTip(tip)"
                                            x-bind:disabled="applyingChanges"
                                            class="text-[10px] font-semibold px-2.5 py-1 rounded-md bg-accent-blue/15 text-accent-blue hover:bg-accent-blue/25 transition disabled:opacity-50"
                                        >
                                            {{ __('coaching.advisor_apply') }}
                                        </button>
                                    </template>
                                    <button
                                        type="button"
                                        @click="dismissHalfTimeTip(tip)"
                                        class="text-[10px] font-medium px-2.5 py-1 rounded-md text-text-muted hover:text-text-secondary transition"
                                    >
                                        {{ __('coaching.advisor_dismiss') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
            {{-- Empty state once everything is dismissed --}}
            <template x-if="halfTimeTips.every(t => halfTimeTipsDismissed.includes(t.id))">
                <p class="px-3 py-3 text-[11px] text-text-muted italic text-center">
                    {{ __('coaching.advisor_all_dismissed') }}
                </p>
            </template>
        </div>
    </template>
</div>
