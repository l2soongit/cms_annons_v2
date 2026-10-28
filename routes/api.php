<?php

use App\Http\Controllers\Api\ServerApiController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\AuthenticateServerKey;
use Illuminate\Support\Facades\Route;

Route::post('telegram/webhook/{secret}', TelegramWebhookController::class)->middleware('throttle:120,1');

// Зворотний виклик платіжної системи про поповнення балансу; довіряємо лише підпису запиту
Route::post('billing/callback/{gateway}', [BillingController::class, 'callback'])->middleware('throttle:120,1')->name('billing.callback');

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('servers/{server:id}', [ServerApiController::class, 'show']);
    Route::get('games/{game:slug}/top', [ServerApiController::class, 'top']);

    Route::middleware(AuthenticateServerKey::class)->group(function () {
        Route::get('servers/{server:id}/votes', [ServerApiController::class, 'votes']);
        Route::get('servers/{server:id}/votes/check', [ServerApiController::class, 'checkVote']);
        Route::post('servers/{server:id}/online', [ServerApiController::class, 'pushOnline']);
    });
});
