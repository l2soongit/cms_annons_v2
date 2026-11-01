<?php

namespace Tests\Feature;

use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ігри в шапці: логотип + коротка назва (зайві — у «Ще»), на головній — випадне меню під пунктом «Ігри».
 */
class HeaderGamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GameSeeder::class);
    }

    public function test_game_page_header_lists_every_game_with_logo_and_short_name(): void
    {
        $this->get('/lineage2')->assertOk()
            ->assertSee('class="gn-item"', false)
            ->assertSee('/images/games/wow.png', false)
            ->assertSeeInOrder(['<span class="gn-short">L2</span>', '<span class="gn-short">GTA</span>', '<span class="gn-short">WoW</span>', '<span class="gn-short">MU</span>'], false)
            ->assertSee('data-gn-more', false)
            // Мова й вхід — у підвалі, не в шапці
            ->assertDontSee('<span class="header-cta">', false)
            ->assertSee('class="foot-tools"', false);
    }

    public function test_home_opens_games_menu_from_the_games_nav_item(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-mode="menu"', false)
            ->assertSee('World of Warcraft')
            ->assertDontSee('class="gn-item"', false);
    }
}
