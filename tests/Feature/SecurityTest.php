<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Безпека й GDPR (ТЗ 4): заголовки безпеки, HSTS на робочому сайті, повідомлення про cookies, політика.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_on_every_page(): void
    {
        $response = $this->get('/search')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_only_in_production_over_https(): void
    {
        $this->app['env'] = 'production';

        $this->get('https://localhost/search')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->get('http://localhost/search')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_cookie_notice_and_privacy_policy(): void
    {
        $this->get('/')->assertOk()->assertSee('id="cookie-notice"', false)->assertSee(route('pages.show', 'privacy'));

        // Після «Зрозуміло» cookie_notice=1: сервер більше не виводить блок
        $this->withUnencryptedCookie('cookie_notice', '1')->get('/')->assertOk()->assertDontSee('id="cookie-notice"', false);

        $this->get('/page/privacy')->assertOk()->assertSee('Політика конфіденційності')->assertSee('90 днів');
        $this->get('/en/page/privacy')->assertOk()->assertSee('Privacy policy')->assertSee('90 days');
    }
}
