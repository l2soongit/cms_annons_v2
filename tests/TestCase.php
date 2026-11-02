<?php

namespace Tests;

use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Застосунок стартує раніше, ніж RefreshDatabase підключить тестову базу (SQLite у пам'яті),
        // тож налаштування з таблиці settings (напр. валюта після міграції) застосовуємо вже з базою
        try {
            Settings::apply();
        } catch (\Throwable) {
            // Тести без бази
        }
    }
}
