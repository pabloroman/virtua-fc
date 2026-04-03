@php
    /** @var App\Models\Game $game */
    /** @var array $players */
    /** @var array $slots */
    /** @var array $academyPlayers */
    /** @var array $unregistered */
@endphp

<x-app-layout :hide-footer="true">
    <div x-data="squadRegistration({
        players: @js($players),
        slots: @js($slots),
        academyPlayers: @js($academyPlayers),
    })" class="min-h-screen pb-28">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

            {{-- Header --}}
            <div class="text-center mb-6 md:mb-8">
                <x-team-crest :team="$game->team" class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-3 md:mb-4" />
                <h1 class="font-heading text-2xl md:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('squad.registration_title') }}</h1>
                <p class="text-sm text-text-muted">{{ __('squad.registration_subtitle') }}</p>
            </div>

            {{-- Flash Messages --}}
            <x-flash-message type="success" :message="session('success')" class="mb-4" />
            <x-flash-message type="error" :message="session('error')" class="mb-4" />

            {{-- Rules --}}
            <div class="mb-6 bg-surface-800 border border-border-default rounded-xl px-5 py-4">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-accent-blue shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                    <h3 class="text-sm font-semibold text-text-primary">{{ __('squad.registration_rules_title') }}</h3>
                </div>
                <ul class="text-xs text-text-muted space-y-1 ml-6 list-disc">
                    <li>{{ __('squad.registration_rule_first_team') }}</li>
                    <li>{{ __('squad.registration_rule_academy') }}</li>
                    <li>{{ __('squad.registration_rule_unregistered') }}</li>
                </ul>
            </div>

            {{-- Two-column layout --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- ===== LEFT PANEL: Registered Players ===== --}}
                <div>
                    {{-- First Team (1-25) --}}
                    <x-section-card :title="__('squad.first_team_slots')">
                        <x-slot name="badge">
                            <span class="text-xs text-text-muted"><span x-text="firstTeamCount" class="font-semibold text-text-body"></span>/25</span>
                        </x-slot>
                        <div class="divide-y divide-border-default">
                            @for($i = 1; $i <= 25; $i++)
                            <div data-slot="{{ $i }}"
                                 @dragover.prevent="onDragOverSlot($event, {{ $i }})"
                                 @dragleave="onDragLeave()"
                                 @drop="onDropSlot($event, {{ $i }})"
                                 class="flex items-center gap-3 px-4 py-2.5 min-h-[52px] transition-colors"
                                 :class="dropTargetSlot === {{ $i }} ? 'bg-accent-blue/10 border-l-2 border-l-accent-blue' : 'border-l-2 border-l-transparent'">

                                <span class="w-8 text-center text-sm font-bold tabular-nums text-text-secondary shrink-0">{{ $i }}</span>

                                <template x-if="getPlayer({{ $i }})">
                                    <div class="flex items-center gap-3 flex-1 min-w-0 cursor-grab active:cursor-grabbing"
                                         draggable="true"
                                         data-draggable
                                         @dragstart="onDragStart($event, slots[{{ $i }}], {{ $i }})"
                                         @dragend="onDragEnd($event)"
                                         @touchstart="onTouchStart($event, slots[{{ $i }}], {{ $i }})"
                                         @touchmove="onTouchMove($event)"
                                         @touchend="onTouchEnd()">

                                        <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-semibold text-white -skew-x-12"
                                              :class="positionBadgeClass(getPlayer({{ $i }}).position_group)">
                                            <span class="skew-x-12" x-text="getPlayer({{ $i }}).position_abbreviation"></span>
                                        </span>

                                        <span class="text-sm font-medium text-text-primary truncate flex-1 min-w-0" x-text="getPlayer({{ $i }}).name"></span>
                                        <span class="text-xs text-text-muted tabular-nums shrink-0" x-text="getPlayer({{ $i }}).age + ' {{ __('squad.years_abbr') }}'"></span>

                                        <div class="rating-badge w-7 h-7 rounded-md text-[10px] flex items-center justify-center shrink-0"
                                             :class="ratingBadgeClass(getPlayer({{ $i }}).overall)">
                                            <span class="font-heading font-bold" x-text="getPlayer({{ $i }}).overall"></span>
                                        </div>
                                        <button type="button" @click.stop="removeFromSlot({{ $i }})" class="p-1 text-text-faint hover:text-accent-red transition-colors shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </template>
                                <template x-if="!getPlayer({{ $i }})">
                                    <span class="text-xs text-text-faint italic">{{ __('squad.empty_slot') }}</span>
                                </template>
                            </div>
                            @endfor
                        </div>
                    </x-section-card>

                    {{-- Academy (26-99) --}}
                    <x-section-card :title="__('squad.academy_slots')" class="mt-4">
                        <x-slot name="badge">
                            <span class="text-xs text-text-muted"><span x-text="academyPlayers.length" class="font-semibold text-text-body"></span></span>
                        </x-slot>
                        <div data-academy-zone
                             @dragover.prevent="onDragOverAcademy($event)"
                             @dragleave="onDragLeave()"
                             @drop="onDropAcademy($event)"
                             class="divide-y divide-border-default min-h-[52px] transition-colors"
                             :class="dropTargetSlot === 'academy' ? 'bg-accent-blue/5' : ''">

                            <template x-for="(entry, index) in academyPlayers" :key="entry.id">
                                <div class="flex items-center gap-3 px-4 py-2.5 min-h-[52px] cursor-grab active:cursor-grabbing"
                                     draggable="true"
                                     data-draggable
                                     @dragstart="onDragStart($event, entry.id, 'academy')"
                                     @dragend="onDragEnd($event)"
                                     @touchstart="onTouchStart($event, entry.id, 'academy')"
                                     @touchmove="onTouchMove($event)"
                                     @touchend="onTouchEnd()">

                                    {{-- Editable number input --}}
                                    <input type="number" min="26" max="99"
                                           :value="entry.number"
                                           @input.stop="updateAcademyNumber(entry.id, $event.target.value)"
                                           @click.stop
                                           class="w-12 h-8 text-sm font-bold text-center bg-surface-700 border rounded-sm tabular-nums focus:ring-2 focus:ring-accent-blue focus:border-accent-blue [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                           :class="isAcademyNumberValid(entry.number, entry.id) ? 'border-border-strong' : 'border-red-500 bg-accent-red/10'">

                                    <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-semibold text-white -skew-x-12"
                                          :class="positionBadgeClass(players[entry.id].position_group)">
                                        <span class="skew-x-12" x-text="players[entry.id].position_abbreviation"></span>
                                    </span>

                                    <span class="text-sm font-medium text-text-primary truncate flex-1 min-w-0" x-text="players[entry.id].name"></span>
                                    <span class="text-xs text-text-muted tabular-nums shrink-0" x-text="players[entry.id].age + ' {{ __('squad.years_abbr') }}'"></span>

                                    <div class="rating-badge w-7 h-7 rounded-md text-[10px] flex items-center justify-center shrink-0"
                                         :class="ratingBadgeClass(players[entry.id].overall)">
                                        <span class="font-heading font-bold" x-text="players[entry.id].overall"></span>
                                    </div>
                                    <button type="button" @click.stop="removeFromAcademy(entry.id)" class="p-1 text-text-faint hover:text-accent-red transition-colors shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>

                            {{-- Empty state --}}
                            <div x-show="academyPlayers.length === 0" class="px-4 py-6 text-center">
                                <p class="text-xs text-text-faint italic">{{ __('squad.drag_to_assign') }}</p>
                            </div>
                        </div>
                    </x-section-card>
                </div>

                {{-- ===== RIGHT PANEL: Unregistered Players ===== --}}
                <div class="lg:sticky lg:top-4 lg:self-start">
                    <x-section-card :title="__('squad.unregistered_players')">
                        <x-slot name="badge">
                            <span class="text-xs text-text-muted" x-text="unregisteredIds.length + ' {{ __('squad.players_count') }}'"></span>
                        </x-slot>

                        <div data-unregistered-zone
                             @dragover.prevent="onDragOverUnregistered($event)"
                             @dragleave="onDragLeave()"
                             @drop="onDropUnregistered($event)"
                             class="divide-y divide-border-default min-h-[200px] transition-colors lg:max-h-[70vh] lg:overflow-y-auto"
                             :class="dropTargetSlot === 'unregistered' ? 'bg-accent-blue/5' : ''">

                            <template x-for="playerId in sortedUnregistered()" :key="playerId">
                                <div class="flex items-center gap-3 px-4 py-2.5 min-h-[52px] cursor-grab active:cursor-grabbing hover:bg-surface-700/50 transition-colors"
                                     draggable="true"
                                     data-draggable
                                     @dragstart="onDragStart($event, playerId, null)"
                                     @dragend="onDragEnd($event)"
                                     @touchstart="onTouchStart($event, playerId, null)"
                                     @touchmove="onTouchMove($event)"
                                     @touchend="onTouchEnd()">

                                    <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-semibold text-white -skew-x-12"
                                          :class="positionBadgeClass(players[playerId].position_group)">
                                        <span class="skew-x-12" x-text="players[playerId].position_abbreviation"></span>
                                    </span>

                                    <span class="text-sm font-medium text-text-primary truncate flex-1 min-w-0" x-text="players[playerId].name"></span>
                                    <span class="text-xs text-text-muted tabular-nums shrink-0" x-text="players[playerId].age + ' {{ __('squad.years_abbr') }}'"></span>

                                    <div class="rating-badge w-7 h-7 rounded-md text-[10px] flex items-center justify-center shrink-0"
                                         :class="ratingBadgeClass(players[playerId].overall)">
                                        <span class="font-heading font-bold" x-text="players[playerId].overall"></span>
                                    </div>
                                    <button type="button" @click.stop="assignToNextSlot(playerId)" class="p-1 text-text-faint hover:text-accent-green transition-colors shrink-0" title="{{ __('squad.first_team') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="sortedUnregistered().length === 0" class="px-4 py-8 text-center">
                                <p class="text-sm text-text-faint">{{ __('squad.no_players_match_filter') }}</p>
                            </div>
                        </div>
                    </x-section-card>
                </div>
            </div>
        </div>

        {{-- Sticky Bottom Bar --}}
        <div class="fixed bottom-0 left-0 right-0 bg-surface-800/95 backdrop-blur-xs border-t border-border-strong shadow-lg z-30">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-3 md:py-4">
                <div class="flex items-center gap-3 md:gap-4">
                    <div class="flex-1 text-sm text-text-muted">
                        <span class="font-bold transition-colors"
                              :class="registeredCount > 0 ? 'text-text-body' : 'text-text-secondary'"
                              x-text="registeredCount"></span>
                        <span>{{ __('squad.registered_count', ['count' => '']) }}</span>
                    </div>

                    <form method="POST" action="{{ route('game.squad.registration.save', $game->id) }}">
                        @csrf
                        {{-- First team slots --}}
                        <template x-for="n in 25" :key="'slot-' + n">
                            <template x-if="slots[n]">
                                <div>
                                    <input type="hidden" :name="'assignments[' + (n - 1) + '][player_id]'" :value="slots[n]">
                                    <input type="hidden" :name="'assignments[' + (n - 1) + '][number]'" :value="n">
                                </div>
                            </template>
                        </template>
                        {{-- Academy players --}}
                        <template x-for="(entry, index) in academyPlayers" :key="'acad-' + entry.id">
                            <div>
                                <input type="hidden" :name="'assignments[' + (25 + index) + '][player_id]'" :value="entry.id">
                                <input type="hidden" :name="'assignments[' + (25 + index) + '][number]'" :value="entry.number">
                            </div>
                        </template>
                        <x-primary-button color="emerald">
                            {{ __('squad.save_registration') }}
                        </x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
