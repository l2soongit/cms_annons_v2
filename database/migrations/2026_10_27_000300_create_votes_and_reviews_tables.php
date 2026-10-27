<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_hash', 64);
            $table->string('fingerprint', 64)->nullable();
            $table->string('nickname', 64)->nullable();
            $table->string('status')->default('counted');      // counted, suspicious, annulled
            $table->string('callback_status')->default('none'); // none, pending, delivered, failed
            $table->unsignedTinyInteger('callback_attempts')->default(0);
            $table->timestamp('created_at')->index();

            $table->index(['server_id', 'ip_hash', 'created_at']);
            $table->index(['server_id', 'status', 'created_at']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->text('owner_reply')->nullable();
            $table->string('status')->default('published');    // pending, published, hidden
            $table->timestamps();

            $table->unique(['server_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('votes');
    }
};
