<?php

use App\Http\Actions\JoinWaitlist;
use Illuminate\Support\Facades\Route;

Route::post('/waitlist', JoinWaitlist::class);
