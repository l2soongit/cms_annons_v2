<?php

use App\Http\Controllers\CabinetController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\VoteController;
use App\Livewire\Cabinet\ServerForm;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/server/{server}', [ServerController::class, 'show'])->name('servers.show');
Route::get('/server/{server}/vote', [VoteController::class, 'create'])->name('servers.vote');
Route::post('/server/{server}/vote', [VoteController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('servers.vote.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/server/{server}/reviews', [ReviewController::class, 'store'])->middleware('throttle:5,1')->name('reviews.store');
    Route::post('/reviews/{review}/reply', [ReviewController::class, 'reply'])->name('reviews.reply');
    Route::post('/reviews/{review}/report', [ReviewController::class, 'report'])->middleware('throttle:10,1')->name('reviews.report');
    Route::post('/server/{server}/report', [ServerController::class, 'report'])->middleware('throttle:10,1')->name('servers.report');

    Route::prefix('cabinet')->name('cabinet')->group(function () {
        Route::get('/', [CabinetController::class, 'index']);
        Route::get('/servers/create', ServerForm::class)->name('.servers.create');
        Route::get('/servers/{server}', [CabinetController::class, 'show'])->name('.servers.show');
        Route::get('/servers/{server}/edit', ServerForm::class)->name('.servers.edit');
        Route::post('/servers/{server}/verify', [CabinetController::class, 'verify'])->middleware('throttle:6,1')->name('.servers.verify');
        Route::post('/servers/{server}/callback', [CabinetController::class, 'updateCallback'])->name('.servers.callback');
        Route::post('/servers/{server}/callback/test', [CabinetController::class, 'testCallback'])->middleware('throttle:6,1')->name('.servers.callback.test');
        Route::post('/servers/{server}/key', [CabinetController::class, 'regenerateKey'])->middleware('throttle:6,1')->name('.servers.key');
    });
});

// Останнім, бо slug гри перехоплює будь-який сегмент першого рівня
Route::get('/{game}', [GameController::class, 'show'])
    ->where('game', '(?!admin|livewire|api|up|storage|filament|cabinet|login|logout|register|forgot-password|reset-password|email|user|two-factor)[a-z0-9-]+')
    ->name('games.show');
