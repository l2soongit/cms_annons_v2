<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Дозволи RBAC для вже встановленого порталу: створює дозволи й дає модератору набір за замовчуванням.
     * Ролі з уже налаштованими дозволами не змінюються.
     */
    public function up(): void
    {
        Permissions::sync();
    }

    public function down(): void
    {
        //
    }
};
