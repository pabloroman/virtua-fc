@php
/** @var App\Models\TournamentChallenge $challenge */
/** @var App\Models\Team $team */

$stats = $challenge->stats;
$highlights = $challenge->squad_highlights;
$boldPicks = $highlights['bold_picks'] ?? [];
$topScorer = $highlights['top_scorer'] ?? null;

$resultColorMap = [
    'champion'          => ['gradient' => 'from-amber-600 via-amber-500 to-amber-400', 'badge_bg' => 'bg-amber-100 text-amber-800', 'accent' => 'text-amber-600'],
    'runner_up'         => ['gradient' => 'from-slate-700 via-slate-600 to-slate-500', 'badge_bg' => 'bg-slate-200 text-slate-700', 'accent' => 'text-slate-500'],
    'semi_finalist'     => ['gradient' => 'from-blue-700 via-blue-600 to-blue-500', 'badge_bg' => 'bg-blue-100 text-blue-700', 'accent' => 'text-blue-600'],
    'quarter_finalist'  => ['gradient' => 'from-blue-600 via-blue-500 to-blue-400', 'badge_bg' => 'bg-blue-50 text-blue-600', 'accent' => 'text-blue-500'],
];
$colors = $resultColorMap[$challenge->result_label] ?? ['gradient' => 'from-slate-600 via-slate-500 to-slate-400', 'badge_bg' => 'bg-slate-100 text-slate-600', 'accent' => 'text-slate-500'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">

    <title>{{ __('season.challenge_page_title', ['team' => $team->name]) }} - VirtuaFC</title>

    {{-- Open Graph / Social sharing meta --}}
    <meta property="og:title" content="{{ __('season.challenge_og_title', ['team' => $team->name, 'result' => __('season.result_' . $challenge->result_label)]) }}">
    <meta property="og:description" content="{{ __('season.challenge_og_description') }}">
    <meta property="og:type" content="website">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=barlow-semi-condensed:400,600,800&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen bg-gradient-to-bl from-slate-900 via-cyan-950 to-teal-950">

        {{-- Hero Section --}}
        <div class="relative overflow-hidden bg-gradient-to-b {{ $colors['gradient'] }} py-12 md:py-20 pb-20 md:pb-28">
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-20 -left-20 w-60 h-60 bg-white/5 rounded-full"></div>
                <div class="absolute -bottom-10 -right-10 w-80 h-80 bg-white/5 rounded-full"></div>
                @if($challenge->result_label === 'champion')
                <div class="absolute top-8 left-1/4 text-amber-300/30 text-4xl">&#9733;</div>
                <div class="absolute top-16 right-1/4 text-amber-300/30 text-3xl">&#9733;</div>
                <div class="absolute bottom-12 left-1/3 text-amber-300/30 text-2xl">&#9733;</div>
                @endif
            </div>

            <div class="relative max-w-lg mx-auto px-4 text-center">
                <div class="text-5xl md:text-6xl mb-4">&#127942;</div>

                <div class="inline-flex flex-col items-center mb-4">
                    <x-team-crest :team="$team" class="w-20 h-20 md:w-24 md:h-24 drop-shadow-lg" />
                    <div class="mt-2 text-xl md:text-2xl font-bold text-white">{{ $team->name }}</div>
                </div>

                <div class="mb-6">
                    <span class="inline-block px-4 py-1.5 {{ $colors['badge_bg'] }} text-xs font-bold uppercase tracking-widest rounded-full border">
                        {{ __('season.result_' . $challenge->result_label) }}
                    </span>
                </div>

                <p class="text-white/70 text-sm md:text-base font-medium max-w-sm mx-auto">
                    {{ __('season.challenge_hero_text') }}
                </p>
            </div>
        </div>

        {{-- Content --}}
        <div class="max-w-lg mx-auto px-4 -mt-12 relative z-10 pb-12">

            {{-- Stats Card --}}
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-5 md:p-6 mb-6">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">{{ __('season.your_tournament') }}</h2>

                <div class="grid grid-cols-6 gap-1 text-center bg-slate-50 rounded-lg p-3 mb-5">
                    @foreach([
                        ['value' => $stats['played'], 'label' => __('season.played_abbr')],
                        ['value' => $stats['won'], 'label' => __('season.won')],
                        ['value' => $stats['drawn'], 'label' => __('season.drawn')],
                        ['value' => $stats['lost'], 'label' => __('season.lost')],
                        ['value' => $stats['goals_for'], 'label' => __('season.goals_for')],
                        ['value' => $stats['goals_against'], 'label' => __('season.goals_against')],
                    ] as $stat)
                    <div>
                        <div class="text-lg md:text-xl font-bold text-slate-900">{{ $stat['value'] }}</div>
                        <div class="text-[10px] text-slate-400 uppercase">{{ $stat['label'] }}</div>
                    </div>
                    @endforeach
                </div>

                {{-- Top Scorer --}}
                @if($topScorer)
                <div class="flex items-center justify-between bg-amber-50 rounded-lg p-3 mb-4">
                    <div>
                        <div class="text-[10px] text-amber-700 font-semibold uppercase tracking-wide">{{ __('season.top_scorer') }}</div>
                        <div class="text-sm font-bold text-slate-900">{{ $topScorer['name'] }}</div>
                    </div>
                    <div>
                        <span class="text-xl font-bold text-amber-600">{{ $topScorer['goals'] }}</span>
                        <span class="text-xs text-amber-600/70">{{ __('season.goals') }}</span>
                    </div>
                </div>
                @endif

                {{-- Bold Picks --}}
                @if(count($boldPicks) > 0)
                <div>
                    <div class="text-[10px] text-violet-700 font-semibold uppercase tracking-wide mb-2">{{ __('season.bold_picks') }}</div>
                    @foreach($boldPicks as $pick)
                    <div class="flex items-center justify-between py-1.5 {{ !$loop->last ? 'border-b border-slate-100' : '' }}">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-slate-800">{{ $pick['name'] }}</span>
                            <span class="text-[10px] text-violet-600 bg-violet-50 px-1.5 py-0.5 rounded font-semibold">{{ $pick['overall'] }} OVR</span>
                        </div>
                        <div class="text-xs text-slate-400">
                            @if($pick['goals'] > 0){{ $pick['goals'] }}G @endif
                            @if($pick['assists'] > 0){{ $pick['assists'] }}A @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Challenge CTA Card --}}
            <div class="bg-gradient-to-br from-slate-800 to-slate-900 rounded-xl shadow-lg p-6 md:p-8 text-center">
                <h2 class="text-xl md:text-2xl font-extrabold text-white mb-2">{{ __('season.can_you_do_better') }}</h2>
                <p class="text-sm text-slate-400 mb-6">{{ __('season.challenge_cta_subtitle', ['team' => $team->name]) }}</p>

                <div class="space-y-3">
                    {{-- Accept Their Squad --}}
                    @auth
                    <form method="POST" action="{{ route('challenge.accept', $challenge->share_token) }}">
                        @csrf
                        <input type="hidden" name="mode" value="same_squad">
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-600 hover:to-yellow-500 text-slate-900 rounded-lg text-sm font-bold shadow-lg transition-all min-h-[44px]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ __('season.accept_their_squad') }}
                        </button>
                    </form>
                    <p class="text-xs text-slate-500">{{ __('season.accept_their_squad_desc') }}</p>

                    <div class="flex items-center gap-3 my-2">
                        <div class="flex-1 border-t border-slate-700"></div>
                        <span class="text-xs text-slate-500 uppercase font-semibold">{{ __('app.or') }}</span>
                        <div class="flex-1 border-t border-slate-700"></div>
                    </div>

                    {{-- Pick Your Own Squad --}}
                    <form method="POST" action="{{ route('challenge.accept', $challenge->share_token) }}">
                        @csrf
                        <input type="hidden" name="mode" value="own_squad">
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-white/10 hover:bg-white/15 text-white border border-white/20 rounded-lg text-sm font-semibold transition-all min-h-[44px]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            {{ __('season.pick_your_own_squad') }}
                        </button>
                    </form>
                    <p class="text-xs text-slate-500">{{ __('season.pick_your_own_squad_desc') }}</p>
                    @else
                    {{-- Not logged in --}}
                    <a href="{{ route('login') }}"
                       class="w-full inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-600 hover:to-yellow-500 text-slate-900 rounded-lg text-sm font-bold shadow-lg transition-all min-h-[44px]">
                        {{ __('season.sign_in_to_play') }}
                    </a>
                    <p class="text-xs text-slate-500 mt-2">
                        {{ __('season.no_account_yet') }}
                        <a href="{{ route('register') }}" class="text-amber-400 hover:text-amber-300 font-semibold">{{ __('season.create_account') }}</a>
                    </p>
                    @endauth
                </div>
            </div>

            {{-- Branding Footer --}}
            <div class="text-center mt-8">
                <span class="text-sm font-bold text-slate-500 tracking-wider">VIRTUAFC</span>
                <p class="text-xs text-slate-600 mt-1">{{ __('season.challenge_footer') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
