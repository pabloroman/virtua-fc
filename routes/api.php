<?php

use App\Http\Actions\HandlePaymentWebhook;
use App\Http\Actions\JoinWaitlist;
use Illuminate\Support\Facades\Route;

Route::post('/waitlist', JoinWaitlist::class);
Route::post('/webhooks/ko-fi', HandlePaymentWebhook::class);
