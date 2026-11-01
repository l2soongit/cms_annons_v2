<?php

use App\Update\Updater;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Сайт ще не встановлено (немає .env) — на інсталятор
if (! file_exists(__DIR__.'/../.env') && file_exists(__DIR__.'/install.php')) {
    header('Location: /install.php', true, 302);
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Нову версію залили на хостинг вручну — прибрати старий кеш, міграції виконає cron або сторінка «Оновлення»
Updater::adoptManualUpload(dirname(__DIR__));

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
