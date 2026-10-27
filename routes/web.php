<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServerController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/server/{server}', [ServerController::class, 'show'])->name('servers.show');

// Останнім, бо slug гри перехоплює будь-який сегмент першого рівня
Route::get('/{game}', [GameController::class, 'show'])
    ->where('game', '(?!admin|livewire|api|up|storage|filament)[a-z0-9-]+')
    ->name('games.show');
