<?php

use App\Http\Actions\InitGame;
use App\Http\Controllers\ProfileController;
use App\Http\Views\Dashboard;
use App\Http\Views\SelectTeam;
use App\Http\Views\ShowGame;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('login');
});

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/select-team', SelectTeam::class)->name('select-team');
    Route::post('/init-game', InitGame::class)->name('init-game');
    Route::get('/game/{id}', ShowGame::class)->name('show-game');
});

Route::middleware('auth')->group(function () {

//    Route::get('/game/{game}/news', News::class)->name('game.news');
//    Route::get('/game/{game}/result/{result}', Result::class)->name('game.result');
//    Route::get('/game/{game}/results', Results::class)->name('game.results');
//    Route::get('/game/{game}/calendar/{competition}', Calendar::class)->name('game.calendar');
//    Route::get('/game/{game}/standings', Standings::class)->name('game.standings');
//    Route::get('/game/{game}/lineup', Lineup::class)->name('game.lineup');
//    Route::get('/game/{game}/transfers', Transfers::class)->name('game.transfers');
//    Route::get('/game/{game}/transfer-map', TransferMap::class)->name('game.transfer-map');
//
//    Route::get('/game/{game}/signings', Signings::class)->name('game.signings');
//    Route::get('/game/{game}/squad', Squad::class)->name('game.squad');
//    Route::get('/game/{game}/budget', Budget::class)->name('game.budget');
//    Route::get('/game/{game}/stadium', Stadium::class)->name('game.stadium');
//    Route::get('/game/{game}/season-overview', SeasonOverview::class)->name('game.season-overview');
//
//    Route::post('/game/{game}/lineup/update', UpdateLineup::class)->name('game.lineup.update');
//    Route::post('/game/{game}/player/renew', RenewPlayer::class)->name('game.player.renew');
//    Route::post('/game/{game}/player/sell', SellPlayer::class)->name('game.player.sell');
//    Route::post('/game/{game}/player/terminate', TerminatePlayer::class)->name('game.player.terminate');
//    Route::post('/game/{game}/make-offer', MakeOffer::class)->name('game.player.sign');
//    Route::post('/game/{game}/decisions', CommitDecisions::class)->name('game.decision.post');
//    Route::post('/game/{game}/season/init', LegacyInitSeason::class)->name('game.season.init');
//    Route::post('/game/{game}/play', PlayMatchday::class)->name('game.matchday.play');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
