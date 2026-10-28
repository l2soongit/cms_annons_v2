<?php

use App\Support\ContentTranslations;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Російські й польські переклади вмісту (ігри, сторінки, тарифи, поля й варіанти), опис DayZ і Rust усіма мовами,
     * «1 day» в англійських назвах тарифів. Змінені в адмінці тексти не перезаписуються.
     */
    public function up(): void
    {
        ContentTranslations::fill();
    }

    public function down(): void
    {
        //
    }
};
