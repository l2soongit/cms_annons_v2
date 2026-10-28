<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Filament\Pages\Settings as SettingsPage;
use App\Filament\Pages\Translations as TranslationsPage;
use App\Filament\Resources\GameResource\Pages\EditGame;
use App\Models\Game;
use App\Models\Server;
use App\Models\User;
use App\Support\Translations;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Мультимовність (ТЗ 4): мова в URL (/en/…), hreflang, переклади з адмінки, нова мова без зміни коду, сторінки.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, GameSeeder::class]);
    }

    private function server(array $fields = []): Server
    {
        $game = Game::where('slug', 'lineage2')->first();
        $server = Server::create([
            'game_id' => $game->id,
            'user_id' => User::factory()->create()->id,
            'name' => 'Aden Classic',
            'slug' => 'aden-classic',
            'host' => 'aden.example.net',
            'status' => ServerStatus::Published,
            'description' => ['uk' => 'Український опис', 'en' => 'English description'],
        ]);
        foreach ($fields as $key => $value) {
            $server->fieldValues()->create(['game_field_id' => $game->fields()->where('key', $key)->value('id'), 'value' => $value]);
        }

        return $server;
    }

    public function test_english_pages_live_under_en_prefix_with_hreflang(): void
    {
        $server = $this->server(['type' => 'gaidard']);

        $uk = $this->get('/lineage2')->assertOk()->assertSee('<html lang="uk"', false)->assertSee('Гайдард');
        $uk->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/lineage2').'">', false);

        $this->get('/en/lineage2')->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('<link rel="canonical" href="'.url('/en/lineage2').'">', false)
            ->assertSee('<link rel="alternate" hreflang="uk" href="'.url('/lineage2').'">', false)
            ->assertSee('hreflang="x-default" href="'.url('/lineage2').'"', false)
            // Посилання на сторінці — англійською, варіанти полів — з перекладом з адмінки
            ->assertSee(url('/en/server/'.$server->slug), false)
            ->assertSee('Hardcore')
            ->assertDontSee('Гайдард')
            // Збірка фронтенду — від кореня сайту
            ->assertDontSee('/en/build/', false);

        $this->get('/en/server/'.$server->slug)->assertOk()->assertSee('English description')->assertDontSee('Український опис');
        // Наступний запит без префікса — знову українська
        $this->get('/server/'.$server->slug)->assertOk()->assertSee('Український опис')->assertSee('<html lang="uk"', false);
    }

    public function test_user_locale_follows_the_site_language_for_emails(): void
    {
        $user = User::factory()->create(['locale' => 'uk']);

        $this->actingAs($user)->get('/en/cabinet')->assertOk();

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_admin_overrides_interface_text_and_empty_value_restores_file(): void
    {
        $this->server();
        Translations::save('en', 'ui.footer.rules', 'Community rules');

        $this->get('/en/search')->assertOk()->assertSee('Community rules');
        $this->get('/search')->assertOk()->assertSee('Правила');

        Translations::save('en', 'ui.footer.rules', '');
        $this->get('/en/search')->assertOk()->assertDontSee('Community rules')->assertSee('Rules');
    }

    public function test_translations_admin_page_saves_a_row(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));

        Livewire::test(TranslationsPage::class)
            ->assertSet('locale', 'en')
            ->set('group', 'billing')
            ->set('search', 'ads.nav')
            ->assertSee('billing.ads.nav')
            ->set('values.billing__ads__nav', 'Banner ads')
            ->call('saveRow', 'billing.ads.nav');

        app()->setLocale('en');
        $this->assertSame('Banner ads', __('billing.ads.nav'));
    }

    public function test_new_language_works_without_code_changes(): void
    {
        config(['graj.locales' => ['uk' => 'Українська', 'en' => 'English', 'de' => 'Deutsch']]);
        Translations::save('de', 'ui.footer.rules', 'Portalregeln');

        $this->get('/de/search')->assertOk()
            ->assertSee('<html lang="de"', false)
            ->assertSee('Portalregeln')
            // Решта текстів — резервною мовою (англійською), поки немає перекладу
            ->assertSee('Search servers')
            ->assertSee('hreflang="de"', false);

        // Форма гри в адмінці отримує поля для нової мови
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        Livewire::test(EditGame::class, ['record' => Game::first()->getRouteKey()])->assertSee('Назва (de)');
    }

    public function test_portal_settings_form_saves_languages_and_vote_interval(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));

        Livewire::test(SettingsPage::class)
            ->assertSet('data.voting.interval_hours', 24)
            ->set('data.voting.interval_hours', 12)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(12, config('graj.voting.interval_hours'));
        $this->assertSame(['uk' => 'Українська', 'en' => 'English', 'ru' => 'Русский', 'pl' => 'Polski'], config('graj.locales'));
        $this->get('/pl/search')->assertOk()->assertSee('<html lang="pl"', false);

        // Без основної мови або з некоректним кодом — не зберігається
        Livewire::test(SettingsPage::class)
            ->set('data.locales', [['key' => 'en', 'value' => 'English'], ['key' => 'polish', 'value' => 'Polski']])
            ->call('save')
            ->assertHasErrors('data.locales');
    }

    public function test_static_pages_in_both_languages(): void
    {
        $this->get('/page/rules')->assertOk()->assertSee('Правила порталу');
        $this->get('/en/page/rules')->assertOk()->assertSee('Portal rules');
        $this->get('/page/missing')->assertNotFound();
        $this->get('/search')->assertSee(route('pages.show', 'faq'));
    }
}
