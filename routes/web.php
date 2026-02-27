<?php

use App\Http\Actions\StartImpersonation;
use App\Http\Actions\StopImpersonation;
use App\Http\Views\AdminUsers;
use App\Http\Actions\DeleteGame;
use App\Http\Actions\AcceptCounterOffer;
use App\Http\Actions\CompleteOnboarding;
use App\Http\Actions\CompleteWelcome;
use App\Http\Actions\AcceptTransferOffer;
use App\Http\Actions\SignFreeAgent;
use App\Http\Actions\DeclineRenewal;
use App\Http\Actions\ReconsiderRenewal;
use App\Http\Actions\AdvanceMatchday;
use App\Http\Actions\SimulateTournament;
use App\Http\Actions\CancelScoutSearch;
use App\Http\Actions\MarkAllNotificationsRead;
use App\Http\Actions\MarkNotificationRead;
use App\Http\Actions\SaveBudgetAllocation;
use App\Http\Views\ShowBudgetAllocation;
use App\Http\Actions\FinalizeMatch;
use App\Http\Actions\GetAutoLineup;
use App\Http\Actions\ProcessExtraTime;
use App\Http\Actions\ProcessPenalties;
use App\Http\Actions\InitGame;
use App\Http\Actions\ListPlayerForTransfer;
use App\Http\Actions\AcceptRenewalCounter;
use App\Http\Actions\SubmitRenewalOffer;
use App\Http\Actions\RejectTransferOffer;
use App\Http\Actions\RequestLoan;
use App\Http\Actions\SaveLineup;
use App\Http\Actions\SaveSquadSelection;
use App\Http\Views\ShowSquadSelection;
use App\Http\Actions\SubmitScoutSearch;
use App\Http\Actions\SubmitPreContractOffer;
use App\Http\Actions\ToggleShortlist;
use App\Http\Actions\RemoveFromShortlist;
use App\Http\Actions\DeleteScoutSearch;
use App\Http\Actions\SubmitTransferBid;
use App\Http\Actions\UnlistPlayerFromTransfer;
use App\Http\Views\ShowLineup;
use App\Http\Controllers\ProfileController;
use App\Http\Views\Dashboard;
use App\Http\Views\SelectTeam;
use App\Http\Views\ShowCalendar;
use App\Http\Views\ShowFinances;
use App\Http\Views\ShowGame;
use App\Http\Views\ShowOnboarding;
use App\Http\Views\ShowWelcome;
use App\Http\Views\ShowCompetition;
use App\Http\Views\ShowLiveMatch;
use App\Http\Views\ShowMatchResults;
use App\Http\Views\ShowScouting;
use App\Http\Views\ShowScoutingHub;
use App\Http\Views\ShowScoutReportResults;
use App\Http\Views\ShowSeasonEnd;
use App\Http\Views\ShowTournamentEnd;
use App\Http\Actions\DismissAcademyPlayer;
use App\Http\Actions\EvaluateAcademy;
use App\Http\Actions\LoanAcademyPlayer;
use App\Http\Views\ShowAcademy;
use App\Http\Views\ShowAcademyEvaluation;
use App\Http\Views\GameSetupStatus;
use App\Http\Views\ShowAcademyPlayerDetail;
use App\Http\Views\ShowPlayerDetail;
use App\Http\Views\ShowSquadV2;
use App\Http\Views\ShowTransferActivity;
use App\Http\Views\ShowTransfers;
use App\Http\Actions\ProcessSubstitution;
use App\Http\Actions\ProcessTacticalChange;
use App\Http\Actions\PromoteAcademyPlayer;
use App\Http\Actions\StartNewSeason;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('login');
});

Route::get('/legal', fn () => view('legal'))->name('legal');
Route::get('/design-system', fn () => view('design-system.index', [
    'allTeams' => \App\Support\TeamColors::all(),
]))->name('design-system');

Route::middleware('auth')->group(function () {
    // Dashboard & Game Creation
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/new-game', SelectTeam::class)->name('select-team');
    Route::post('/new-game', InitGame::class)->name('init-game');

    // All game routes require ownership verification
    Route::middleware('game.owner')->group(function () {
        Route::delete('/game/{gameId}', DeleteGame::class)->name('game.destroy');

        // Game Views
        Route::get('/game/{gameId}', ShowGame::class)->name('show-game');
        Route::get('/game/{gameId}/squad', ShowSquadV2::class)->name('game.squad');
        Route::get('/game/{gameId}/squad/academy', ShowAcademy::class)->name('game.squad.academy');
        Route::get('/game/{gameId}/player/{playerId}/detail', ShowPlayerDetail::class)->name('game.player.detail');
        Route::get('/game/{gameId}/academy/{playerId}/detail', ShowAcademyPlayerDetail::class)->name('game.academy.detail');
        Route::post('/game/{gameId}/academy/{playerId}/promote', PromoteAcademyPlayer::class)->name('game.academy.promote');
        Route::post('/game/{gameId}/academy/{playerId}/loan', LoanAcademyPlayer::class)->name('game.academy.loan');
        Route::post('/game/{gameId}/academy/{playerId}/dismiss', DismissAcademyPlayer::class)->name('game.academy.dismiss');
        Route::get('/game/{gameId}/squad/academy/evaluate', ShowAcademyEvaluation::class)->name('game.squad.academy.evaluate');
        Route::post('/game/{gameId}/squad/academy/evaluate', EvaluateAcademy::class)->name('game.squad.academy.evaluate.submit');
        Route::get('/game/{gameId}/finances', ShowFinances::class)->name('game.finances');
        Route::get('/game/{gameId}/transfers', ShowScouting::class)->name('game.transfers');
        Route::get('/game/{gameId}/transfers/outgoing', ShowTransfers::class)->name('game.transfers.outgoing');
        Route::get('/game/{gameId}/transfer-activity', ShowTransferActivity::class)->name('game.transfer-activity');
        Route::get('/game/{gameId}/calendar', ShowCalendar::class)->name('game.calendar');
        Route::get('/game/{gameId}/competition/{competitionId}', ShowCompetition::class)->name('game.competition');
        Route::get('/game/{gameId}/results/{competition}/{matchday}', ShowMatchResults::class)->name('game.results');
        Route::get('/game/{gameId}/live/{matchId}', ShowLiveMatch::class)->name('game.live-match');
        Route::get('/game/{gameId}/lineup', ShowLineup::class)->name('game.lineup');

        // Game Actions
        Route::post('/game/{gameId}/advance', AdvanceMatchday::class)->name('game.advance');
        Route::post('/game/{gameId}/lineup', SaveLineup::class)->name('game.lineup.save');
        Route::get('/game/{gameId}/lineup/auto', GetAutoLineup::class)->name('game.lineup.auto');
        Route::post('/game/{gameId}/match/{matchId}/substitute', ProcessSubstitution::class)->name('game.match.substitute');
        Route::post('/game/{gameId}/match/{matchId}/tactics', ProcessTacticalChange::class)->name('game.match.tactics');
        Route::post('/game/{gameId}/match/{matchId}/extra-time', ProcessExtraTime::class)->name('game.match.extra-time');
        Route::post('/game/{gameId}/match/{matchId}/penalties', ProcessPenalties::class)->name('game.match.penalties');
        Route::post('/game/{gameId}/finalize-match', FinalizeMatch::class)->name('game.finalize-match');
        // Transfers
        Route::post('/game/{gameId}/transfers/list/{playerId}', ListPlayerForTransfer::class)->name('game.transfers.list');
        Route::post('/game/{gameId}/transfers/unlist/{playerId}', UnlistPlayerFromTransfer::class)->name('game.transfers.unlist');
        Route::post('/game/{gameId}/transfers/accept/{offerId}', AcceptTransferOffer::class)->name('game.transfers.accept');
        Route::post('/game/{gameId}/transfers/reject/{offerId}', RejectTransferOffer::class)->name('game.transfers.reject');
        Route::post('/game/{gameId}/transfers/renew/{playerId}', SubmitRenewalOffer::class)->name('game.transfers.renew');
        Route::post('/game/{gameId}/transfers/accept-counter/{playerId}', AcceptRenewalCounter::class)->name('game.transfers.accept-renewal-counter');
        Route::post('/game/{gameId}/transfers/decline-renewal/{playerId}', DeclineRenewal::class)->name('game.transfers.decline-renewal');
        Route::post('/game/{gameId}/transfers/reconsider-renewal/{playerId}', ReconsiderRenewal::class)->name('game.transfers.reconsider-renewal');

        // Scouting
        Route::get('/game/{gameId}/scouting', ShowScoutingHub::class)->name('game.scouting');
        Route::get('/game/{gameId}/scouting/{reportId}/results', ShowScoutReportResults::class)->name('game.scouting.results');
        Route::post('/game/{gameId}/scouting/search', SubmitScoutSearch::class)->name('game.scouting.search');
        Route::post('/game/{gameId}/scouting/cancel', CancelScoutSearch::class)->name('game.scouting.cancel');
        Route::post('/game/{gameId}/scouting/{playerId}/bid', SubmitTransferBid::class)->name('game.scouting.bid');
        Route::post('/game/{gameId}/scouting/{playerId}/loan', RequestLoan::class)->name('game.scouting.loan');
        Route::post('/game/{gameId}/scouting/{playerId}/sign-free-agent', SignFreeAgent::class)->name('game.scouting.sign-free-agent');
        Route::post('/game/{gameId}/scouting/{playerId}/pre-contract', SubmitPreContractOffer::class)->name('game.scouting.pre-contract');
        Route::post('/game/{gameId}/scouting/counter/{offerId}/accept', AcceptCounterOffer::class)->name('game.scouting.counter.accept');
        Route::post('/game/{gameId}/scouting/shortlist/{playerId}', ToggleShortlist::class)->name('game.scouting.shortlist.toggle');
        Route::post('/game/{gameId}/scouting/shortlist/{playerId}/remove', RemoveFromShortlist::class)->name('game.scouting.shortlist.remove');
        Route::delete('/game/{gameId}/scouting/{reportId}', DeleteScoutSearch::class)->name('game.scouting.delete');

        // Loans (redirect old URL to transfers)
        Route::get('/game/{gameId}/loans', function (string $gameId) {
            return redirect()->route('game.transfers.outgoing', $gameId);
        })->name('game.loans');
        Route::post('/game/{gameId}/loans/out/{playerId}', RequestLoan::class)->name('game.loans.out');

        // Season End
        Route::get('/game/{gameId}/season-end', ShowSeasonEnd::class)->name('game.season-end');
        Route::post('/game/{gameId}/start-new-season', StartNewSeason::class)->name('game.start-new-season');

        // Tournament End
        Route::get('/game/{gameId}/tournament-end', ShowTournamentEnd::class)->name('game.tournament-end');
        Route::get('/game/{gameId}/simulate-tournament', SimulateTournament::class)->name('game.simulate-tournament');

        // Budget Allocation
        Route::get('/game/{gameId}/budget', ShowBudgetAllocation::class)->name('game.budget');
        Route::post('/game/{gameId}/budget', SaveBudgetAllocation::class)->name('game.budget.save');

        // Welcome Tutorial (new games only)
        Route::get('/game/{gameId}/welcome', ShowWelcome::class)->name('game.welcome');
        Route::post('/game/{gameId}/welcome', CompleteWelcome::class)->name('game.welcome.complete');

        // Onboarding (season budget allocation)
        Route::get('/game/{gameId}/onboarding', ShowOnboarding::class)->name('game.onboarding');
        Route::post('/game/{gameId}/onboarding', CompleteOnboarding::class)->name('game.onboarding.complete');

        // Squad Selection (Tournament mode onboarding)
        Route::get('/game/{gameId}/squad-selection', ShowSquadSelection::class)->name('game.squad-selection');
        Route::post('/game/{gameId}/squad-selection', SaveSquadSelection::class)->name('game.squad-selection.save');

        // Game Setup Status (polling endpoint)
        Route::get('/game/{gameId}/setup-status', GameSetupStatus::class)->name('game.setup-status');

        // Notifications
        Route::post('/game/{gameId}/notifications/{notificationId}/read', MarkNotificationRead::class)->name('game.notifications.read');
        Route::post('/game/{gameId}/notifications/read-all', MarkAllNotificationsRead::class)->name('game.notifications.read-all');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    // Accessible while impersonating (impersonated user may not be admin)
    Route::post('/stop-impersonation', StopImpersonation::class)->name('stop-impersonation');

    // Admin-only routes
    Route::middleware('admin')->group(function () {
        Route::get('/users', AdminUsers::class)->name('users');
        Route::post('/impersonate/{userId}', StartImpersonation::class)->name('impersonate');
    });
});

require __DIR__.'/auth.php';
