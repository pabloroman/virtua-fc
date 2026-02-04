<?php

use App\Http\Actions\AcceptTransferOffer;
use App\Http\Actions\AdvanceMatchday;
use App\Http\Actions\AdvancePreseasonWeek;
use App\Http\Actions\ConductCupDraw;
use App\Http\Actions\GetAutoLineup;
use App\Http\Actions\InitGame;
use App\Http\Actions\ListPlayerForTransfer;
use App\Http\Actions\OfferRenewal;
use App\Http\Actions\RejectTransferOffer;
use App\Http\Actions\SaveLineup;
use App\Http\Actions\UnlistPlayerFromTransfer;
use App\Http\Views\ShowLineup;
use App\Http\Controllers\ProfileController;
use App\Http\Views\Dashboard;
use App\Http\Views\SelectTeam;
use App\Http\Views\ShowCalendar;
use App\Http\Views\ShowFinances;
use App\Http\Views\ShowGame;
use App\Http\Views\ShowCompetition;
use App\Http\Views\ShowMatchResults;
use App\Http\Views\ShowSquad;
use App\Http\Views\ShowContracts;
use App\Http\Views\ShowPreseason;
use App\Http\Views\ShowSeasonEnd;
use App\Http\Views\ShowSquadDevelopment;
use App\Http\Views\ShowSquadStats;
use App\Http\Views\ShowTransfers;
use App\Http\Actions\ProcessSeasonDevelopment;
use App\Http\Actions\StartNewSeason;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('login');
});

Route::middleware('auth')->group(function () {
    // Dashboard & Game Creation
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/new-game', SelectTeam::class)->name('select-team');
    Route::post('/new-game', InitGame::class)->name('init-game');

    // Game Views
    Route::get('/game/{gameId}', ShowGame::class)->name('show-game');
    Route::get('/game/{gameId}/squad', ShowSquad::class)->name('game.squad');
    Route::get('/game/{gameId}/squad/development', ShowSquadDevelopment::class)->name('game.squad.development');
    Route::get('/game/{gameId}/squad/contracts', ShowContracts::class)->name('game.squad.contracts');
    Route::get('/game/{gameId}/squad/stats', ShowSquadStats::class)->name('game.squad.stats');
    Route::get('/game/{gameId}/finances', ShowFinances::class)->name('game.finances');
    Route::get('/game/{gameId}/transfers', ShowTransfers::class)->name('game.transfers');
    Route::get('/game/{gameId}/calendar', ShowCalendar::class)->name('game.calendar');
    Route::get('/game/{gameId}/competition/{competitionId}', ShowCompetition::class)->name('game.competition');
    Route::get('/game/{gameId}/results/{competition}/{matchday}', ShowMatchResults::class)->name('game.results');
    Route::get('/game/{gameId}/lineup/{matchId}', ShowLineup::class)->name('game.lineup');

    // Game Actions
    Route::post('/game/{gameId}/advance', AdvanceMatchday::class)->name('game.advance');
    Route::post('/game/{gameId}/lineup/{matchId}', SaveLineup::class)->name('game.lineup.save');
    Route::get('/game/{gameId}/lineup/{matchId}/auto', GetAutoLineup::class)->name('game.lineup.auto');
    Route::post('/game/{gameId}/cup/draw/{round}', ConductCupDraw::class)->name('game.cup.draw');
    Route::post('/game/{gameId}/development/process', ProcessSeasonDevelopment::class)->name('game.development.process');

    // Transfers
    Route::post('/game/{gameId}/transfers/list/{playerId}', ListPlayerForTransfer::class)->name('game.transfers.list');
    Route::post('/game/{gameId}/transfers/unlist/{playerId}', UnlistPlayerFromTransfer::class)->name('game.transfers.unlist');
    Route::post('/game/{gameId}/transfers/accept/{offerId}', AcceptTransferOffer::class)->name('game.transfers.accept');
    Route::post('/game/{gameId}/transfers/reject/{offerId}', RejectTransferOffer::class)->name('game.transfers.reject');
    Route::post('/game/{gameId}/transfers/renew/{playerId}', OfferRenewal::class)->name('game.transfers.renew');

    // Season End
    Route::get('/game/{gameId}/season-end', ShowSeasonEnd::class)->name('game.season-end');
    Route::post('/game/{gameId}/start-new-season', StartNewSeason::class)->name('game.start-new-season');

    // Pre-season
    Route::get('/game/{gameId}/preseason', ShowPreseason::class)->name('game.preseason');
    Route::post('/game/{gameId}/preseason/advance', AdvancePreseasonWeek::class)->name('game.preseason.advance');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
