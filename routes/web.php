<?php

use App\Http\Actions\InitGame;
use App\Http\Controllers\ProfileController;
use App\Http\Views\Dashboard;
use App\Http\Views\SelectTeam;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('login');
});

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/select-team', SelectTeam::class)->name('select-team');
    Route::post('/init-game', InitGame::class)->name('init-game');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
