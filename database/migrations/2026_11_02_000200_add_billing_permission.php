<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Новий дозвіл «billing.manage» на робочому порталі (адмін має його автоматично) */
    public function up(): void
    {
        Permissions::sync();
    }

    public function down(): void
    {
        //
    }
};
