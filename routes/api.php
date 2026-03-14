<?php

use App\Http\Actions\JoinWaitlist;
use App\Http\Actions\RegisterDeviceToken;
use Illuminate\Support\Facades\Route;

Route::post('/waitlist', JoinWaitlist::class);

Route::middleware('auth:web')->group(function () {
    Route::post('/device-token', RegisterDeviceToken::class);
});
