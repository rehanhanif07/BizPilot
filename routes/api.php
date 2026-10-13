<?php

use App\Http\Controllers\Api\V1\CurrentUserController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)
        ->withoutMiddleware('throttle:api')
        ->name('health');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', CurrentUserController::class)->name('user');
    });
});
