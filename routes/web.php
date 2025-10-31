<?php

use App\Http\Controllers\ActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web'])->group(function () {
    Route::post('/heartbeat', [ActivityController::class, 'heartbeat']);
    Route::post('/activity-logout', [ActivityController::class, 'activityLogout']);
    Route::post('/user-leaving', [ActivityController::class, 'userLeaving']);
});
