<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');                       // {"uk": "...", "en": "..."}
            $table->json('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('cover')->nullable();
            $table->string('query_driver');             // tcp, minecraft, source, samp, fivem, altv, ragemp
            $table->unsignedInteger('default_port')->nullable();
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->unsignedSmallInteger('vip_slots')->default(5);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('game_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('label');
            $table->string('type');                     // text, number, select, multiselect, boolean, date
            $table->json('options')->nullable();        // [{"value": "interlude", "label": {"uk": "Interlude"}}]
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('in_url')->default(false);  // /lineage2/interlude/x100
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['game_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_fields');
        Schema::dropIfExists('games');
    }
};
