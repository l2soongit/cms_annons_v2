<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Спільнота: новини й форум в одному. Новина — тема форуму з типом news / server_news,
     * коментарі під новиною — відповіді в темі. Кожна тема проходить модерацію (status = pending).
     */
    public function up(): void
    {
        Schema::create('forum_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('forum_categories')->nullOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('icon', 16)->nullable();
            $table->string('group', 32)->default('games'); // site | games | other — блок у таблиці розділів
            $table->unsignedInteger('sort')->default(0);
            $table->json('post_roles')->nullable(); // ролі, яким можна створювати теми; null — усім зареєстрованим
            $table->json('topic_types')->nullable(); // дозволені типи тем; null — усі
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('topics_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->timestamps();
        });

        Schema::create('forum_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('forum_categories')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24)->default('discussion');
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body'); // чистий HTML для показу
            $table->longText('source')->nullable(); // текст автора з сайту (Markdown) — для редагування
            $table->string('cover')->nullable();
            $table->string('locale', 8)->default('uk');
            $table->json('meta')->nullable(); // набір у клан: рівень, мова, прайм тощо
            $table->string('status', 16)->default('pending'); // pending | published | rejected | hidden
            $table->string('reject_reason')->nullable();
            $table->string('flag_reason')->nullable(); // чому автофільтр відправив на перевірку
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('pinned_until')->nullable(); // закріплення, куплене за бали
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('best_post_id')->nullable();
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->integer('score')->default(0);
            $table->foreignId('last_post_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_post_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status', 'is_pinned', 'last_post_at']);
            $table->index(['type', 'status']);
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('forum_posts')->nullOnDelete();
            $table->text('body');
            $table->text('source')->nullable();
            $table->string('status', 16)->default('published'); // published | pending | hidden
            $table->string('flag_reason')->nullable();
            $table->integer('score')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['topic_id', 'status', 'created_at']);
        });

        // Лайки тем (▲▼) і відповідей (▲): одна оцінка від користувача на об'єкт
        Schema::create('forum_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('target');
            $table->tinyInteger('value');
            $table->timestamps();
            $table->unique(['user_id', 'target_type', 'target_id']);
        });

        Schema::create('forum_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('subscribable'); // тема, розділ або сервер
            $table->timestamps();
            $table->unique(['user_id', 'subscribable_type', 'subscribable_id'], 'forum_subscriptions_unique');
        });

        Schema::create('forum_polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->unique()->constrained('forum_topics')->cascadeOnDelete();
            $table->string('question');
            $table->json('options');
            $table->boolean('multiple')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forum_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('forum_polls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('option');
            $table->timestamps();
            $table->unique(['poll_id', 'user_id', 'option']);
        });

        // Бонусна програма: бали окремо від грошового балансу
        Schema::table('users', function (Blueprint $table) {
            $table->integer('bonus_balance')->default(0)->after('balance');
            $table->unsignedInteger('bonus_earned')->default(0)->after('bonus_balance'); // усього зароблено — рахує рівень
            $table->unsignedInteger('forum_posts_count')->default(0)->after('bonus_earned');
            $table->json('perks')->nullable()->after('forum_posts_count'); // куплені прикраси: колір ніку, рамка, значок
            $table->unsignedSmallInteger('streak_days')->default(0)->after('perks'); // днів активності поспіль
            $table->date('streak_date')->nullable()->after('streak_days');
        });

        Schema::create('bonus_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason', 40);
            $table->nullableMorphs('subject');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'reason', 'created_at']);
        });

        Schema::create('bonus_rewards', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('kind', 24); // name_color | frame | badge | pin_clan | service_discount
            $table->string('value')->nullable(); // колір, ключ рамки чи значка, відсоток знижки
            $table->unsignedInteger('cost');
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('audience', 16)->default('all'); // all | players | owners
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $this->seedCategories();

        Permissions::sync();
        // Наявному модератору — модерація спільноти (sync дає дозволи лише новим ролям)
        Role::where('name', 'moderator')->where('guard_name', 'web')->first()?->givePermissionTo('community.moderate');
    }

    /** Стартові розділи як у демо: graj.club, ігри, різне */
    private function seedCategories(): void
    {
        $now = now();
        $rows = [
            ['news', ['uk' => 'Новини й оголошення', 'en' => 'News & announcements'], ['uk' => 'Кожна новина сайту — тема з обговоренням', 'en' => 'Every site news is a discussion topic'], '📣', 'site', ['admin', 'moderator'], ['news']],
            ['owners', ['uk' => 'Власникам серверів', 'en' => 'Server owners'], ['uk' => 'API, callback за голос, просування, питання до адміністрації', 'en' => 'API, vote callbacks, promotion, questions'], '🛠', 'site', null, null],
        ];
        $sort = 0;
        foreach ($rows as [$slug, $name, $desc, $icon, $group, $roles, $types]) {
            DB::table('forum_categories')->insert([
                'slug' => $slug, 'name' => json_encode($name, JSON_UNESCAPED_UNICODE), 'description' => json_encode($desc, JSON_UNESCAPED_UNICODE),
                'icon' => $icon, 'group' => $group, 'sort' => $sort += 10, 'post_roles' => $roles ? json_encode($roles) : null,
                'topic_types' => $types ? json_encode($types) : null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (DB::table('games')->orderBy('sort')->get(['id', 'slug', 'name']) as $game) {
            DB::table('forum_categories')->insert([
                'game_id' => $game->id, 'slug' => $game->slug, 'name' => $game->name, 'description' => null, 'icon' => null,
                'group' => 'games', 'sort' => $sort += 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('forum_categories')->insert([
            'slug' => 'offtopic', 'name' => json_encode(['uk' => 'Флудилка', 'en' => 'Off-topic'], JSON_UNESCAPED_UNICODE),
            'description' => json_encode(['uk' => 'Все, що не про сервери', 'en' => 'Anything not about servers'], JSON_UNESCAPED_UNICODE),
            'icon' => '💬', 'group' => 'other', 'sort' => $sort + 10, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_rewards');
        Schema::dropIfExists('bonus_transactions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['bonus_balance', 'bonus_earned', 'forum_posts_count', 'perks', 'streak_days', 'streak_date']));
        Schema::dropIfExists('forum_poll_votes');
        Schema::dropIfExists('forum_polls');
        Schema::dropIfExists('forum_subscriptions');
        Schema::dropIfExists('forum_reactions');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_topics');
        Schema::dropIfExists('forum_categories');
    }
};
