<?php

use App\Http\Controllers\ActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web'])->group(function () {
    Route::post('/heartbeat', [ActivityController::class, 'heartbeat']);
    Route::post('/activity-logout', [ActivityController::class, 'activityLogout']);
    Route::post('/user-leaving', [ActivityController::class, 'userLeaving']);

    Route::get('/users/{user}/login-as', [ActivityController::class, 'loginAs'])
        ->name('users.impersonate');
    Route::get('/impersonate/leave', [ActivityController::class, 'leave'])
        ->name('impersonate.leave');
});
