@props(['game', 'active' => 'market'])

<div class="flex border-b border-slate-200 mb-0">
    <a href="{{ route('game.transfers', $game->id) }}"
       class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $active === 'market' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
        Market
    </a>
    <a href="{{ route('game.scouting', $game->id) }}"
       class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $active === 'scouting' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
        Scouting
    </a>
    <a href="{{ route('game.loans', $game->id) }}"
       class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors {{ $active === 'loans' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}">
        Loans
    </a>
</div>
