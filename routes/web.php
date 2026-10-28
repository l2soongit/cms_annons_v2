<?php

use App\Http\Controllers\AdsController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CabinetController;
use App\Http\Controllers\CoOwnerController;
use App\Http\Controllers\DevelopersController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\VoteController;
use App\Http\Controllers\WidgetController;
use App\Livewire\Cabinet\ServerForm;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/live/feed', [HomeController::class, 'live'])->middleware('throttle:30,1')->name('home.live');
Route::get('/search', SearchController::class)->middleware('throttle:30,1')->name('search');
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->middleware('guest')->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->middleware('guest')->name('social.callback');
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
    Route::post('/server/{server}/message', [MessagesController::class, 'start'])->middleware('throttle:10,1')->name('servers.message');

    Route::prefix('cabinet')->name('cabinet')->group(function () {
        Route::get('/', [CabinetController::class, 'index']);
        Route::get('/servers/create', ServerForm::class)->name('.servers.create');
        Route::get('/servers/{server}', [CabinetController::class, 'show'])->name('.servers.show');
        Route::get('/servers/{server}/edit', ServerForm::class)->name('.servers.edit');
        Route::post('/servers/{server}/verify', [CabinetController::class, 'verify'])->middleware('throttle:6,1')->name('.servers.verify');
        Route::post('/servers/{server}/callback', [CabinetController::class, 'updateCallback'])->name('.servers.callback');
        Route::post('/servers/{server}/callback/test', [CabinetController::class, 'testCallback'])->middleware('throttle:6,1')->name('.servers.callback.test');
        Route::post('/servers/{server}/key', [CabinetController::class, 'regenerateKey'])->middleware('throttle:6,1')->name('.servers.key');

        Route::post('/servers/{server}/owners', [CoOwnerController::class, 'invite'])->middleware('throttle:10,1')->name('.servers.owners.invite');
        Route::delete('/servers/{server}/owners/{user}', [CoOwnerController::class, 'remove'])->name('.servers.owners.remove');
        Route::delete('/servers/{server}/invitations/{invitation}', [CoOwnerController::class, 'cancelInvitation'])->name('.servers.invitations.cancel');
        Route::post('/servers/{server}/transfer', [CoOwnerController::class, 'transfer'])->middleware('throttle:5,1')->name('.servers.transfer');
        Route::get('/invitations/{token}', [CoOwnerController::class, 'accept'])->name('.invitations.accept');
        Route::post('/servers/{server}/orders', [PromotionController::class, 'store'])->middleware('throttle:10,1')->name('.servers.orders');
        Route::post('/orders/{order}/auto-renew', [PromotionController::class, 'autoRenew'])->name('.orders.auto-renew');
        Route::get('/orders/{order}/receipt', [BillingController::class, 'receipt'])->name('.orders.receipt');
        Route::get('/balance', [BillingController::class, 'index'])->name('.balance');
        Route::get('/messages', [MessagesController::class, 'index'])->name('.messages');
        Route::get('/messages/{conversation}', [MessagesController::class, 'show'])->name('.messages.show');
        Route::get('/messages/attachment/{message}', [MessagesController::class, 'attachment'])->name('.messages.attachment');
        Route::get('/support', [SupportController::class, 'index'])->name('.support');
        Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:5,1')->name('.support.store');
        Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('.support.show');
        Route::get('/ads', [AdsController::class, 'index'])->name('.ads');
        Route::post('/ads', [AdsController::class, 'store'])->middleware('throttle:10,1')->name('.ads.store');
        Route::post('/balance', [BillingController::class, 'deposit'])->middleware('throttle:10,1')->name('.balance.deposit');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('.profile');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('.profile.destroy');
        Route::get('/security', [ProfileController::class, 'security'])->middleware('password.confirm')->name('.security');
        Route::get('/notifications', [ProfileController::class, 'notifications'])->name('.notifications');
        Route::put('/notifications', [ProfileController::class, 'updateNotifications'])->name('.notifications.update');
        Route::delete('/notifications/telegram', [ProfileController::class, 'unlinkTelegram'])->name('.notifications.telegram.unlink');
        Route::put('/notifications/discord', [ProfileController::class, 'updateDiscord'])->middleware('throttle:6,1')->name('.notifications.discord');
        Route::get('/inbox', [InboxController::class, 'index'])->name('.inbox');
        Route::get('/inbox/{id}', [InboxController::class, 'open'])->name('.inbox.open');
        Route::post('/inbox/read-all', [InboxController::class, 'readAll'])->name('.inbox.read-all');
    });
});

// Документація API і віджети для сайтів серверів (ТЗ 7.2–7.3)
Route::get('/developers', [DevelopersController::class, 'index'])->name('developers');
Route::get('/developers/openapi.json', [DevelopersController::class, 'openapi'])->name('developers.openapi');
Route::get('/widget/{server:id}/vote.svg', [WidgetController::class, 'vote'])->middleware('throttle:120,1')->name('widgets.vote');
Route::get('/widget/{server:id}/status.svg', [WidgetController::class, 'status'])->middleware('throttle:120,1')->name('widgets.status');

// Перехід з рекламного банера (лічильник кліків)
Route::get('/ad/{banner}', [AdsController::class, 'click'])->middleware('throttle:60,1')->name('ads.click');

// Повернення з платіжної системи й тестова оплата (лише локально, посилання підписане)
Route::get('/billing/return', [BillingController::class, 'returned'])->name('billing.return');
Route::post('/billing/return', [BillingController::class, 'returned']);
Route::get('/billing/test/{transaction}', [BillingController::class, 'testPage'])->middleware(['auth', 'signed'])->name('billing.test');
Route::post('/billing/test', [BillingController::class, 'testPay'])->middleware('auth')->name('billing.test.pay');

// Останнім, бо slug гри перехоплює будь-який сегмент першого рівня
Route::get('/{game}', [GameController::class, 'show'])
    ->where('game', '(?!admin|livewire|api|up|storage|filament|cabinet|billing|developers|widget|search|auth|login|logout|register|forgot-password|reset-password|email|user|two-factor)[a-z0-9-]+')
    ->name('games.show');
