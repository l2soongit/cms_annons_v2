<?php

use App\Http\Controllers\Api\ServerApiController;
use App\Http\Middleware\AuthenticateServerKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('servers/{server:id}', [ServerApiController::class, 'show']);
    Route::get('games/{game:slug}/top', [ServerApiController::class, 'top']);

    Route::middleware(AuthenticateServerKey::class)->group(function () {
        Route::get('servers/{server:id}/votes', [ServerApiController::class, 'votes']);
        Route::get('servers/{server:id}/votes/check', [ServerApiController::class, 'checkVote']);
        Route::post('servers/{server:id}/online', [ServerApiController::class, 'pushOnline']);
    });
});
