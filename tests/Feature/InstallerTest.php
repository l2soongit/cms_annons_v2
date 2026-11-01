<?php

namespace Tests\Feature;

use App\Install\Installer;
use Dotenv\Dotenv;
use Tests\TestCase;

/**
 * Встановлення через браузер (public/install.php): перевірка форми, запис .env, блокування після встановлення.
 */
class InstallerTest extends TestCase
{
    private function form(array $over = []): array
    {
        return $over + [
            'app_url' => 'https://graj.club/', 'db_host' => 'localhost', 'db_port' => '3306', 'db_database' => 'graj', 'db_username' => 'graj', 'db_password' => 'p@ss "word" $x#1',
            'admin_email' => 'boss@graj.club', 'admin_password' => 'long-enough-pass', 'admin_password2' => 'long-enough-pass',
            'mail_host' => 'mail.graj.club', 'mail_port' => '465', 'mail_username' => 'noreply@graj.club', 'mail_password' => 'secret', 'mail_from' => 'noreply@graj.club',
        ];
    }

    public function test_form_validation(): void
    {
        $this->assertSame([], Installer::validate($this->form()));

        $errors = Installer::validate($this->form(['app_url' => 'graj.club', 'db_port' => 'abc', 'admin_password2' => 'other-password', 'db_database' => '']));
        $this->assertCount(4, $errors);

        $this->assertNotEmpty(Installer::validate($this->form(['admin_password' => 'short', 'admin_password2' => 'short'])));
        // Пошта необов'язкова; якщо SMTP задано — потрібна адреса відправника
        $this->assertSame([], Installer::validate($this->form(['mail_host' => '', 'mail_from' => ''])));
        $this->assertNotEmpty(Installer::validate($this->form(['mail_from' => ''])));
    }

    public function test_env_is_written_from_production_template(): void
    {
        $template = file_get_contents(base_path('.env.production.example'));
        $values = Installer::envValues($this->form());
        $env = Installer::envContents($template, $values);

        $this->assertStringContainsString("APP_ENV=production\n", $env);
        $this->assertStringContainsString("APP_DEBUG=false\n", $env);
        $this->assertStringContainsString("APP_URL=https://graj.club\n", $env);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m', $env);
        $this->assertStringContainsString("SESSION_SECURE_COOKIE=true\n", $env);
        $this->assertStringContainsString("MAIL_MAILER=smtp\n", $env);
        $this->assertStringContainsString("MAIL_SCHEME=smtps\n", $env);
        // Ключі шаблону, яких форма не чіпає, лишаються
        $this->assertStringContainsString("QUEUE_CONNECTION=database\n", $env);
        $this->assertSame(1, substr_count($env, "\nDB_PASSWORD="));

        // Пароль зі спецсимволами читається назад без змін
        $parsed = Dotenv::parse($env);
        $this->assertSame('p@ss "word" $x#1', $parsed['DB_PASSWORD']);
        $this->assertSame('graj', $parsed['DB_DATABASE']);
    }

    public function test_http_site_and_no_smtp(): void
    {
        $values = Installer::envValues($this->form(['app_url' => 'http://test.local', 'mail_host' => '', 'mail_from' => '']));

        $this->assertSame('false', $values['SESSION_SECURE_COOKIE']);
        $this->assertSame('log', $values['MAIL_MAILER']);
        $this->assertSame('noreply@test.local', $values['MAIL_FROM_ADDRESS']);
    }

    public function test_requirements_and_lock(): void
    {
        $dir = sys_get_temp_dir().'/graj-install-'.uniqid();
        mkdir("{$dir}/storage", 0777, true);
        $installer = new Installer($dir);

        $checks = $installer->requirements();
        $this->assertFalse(Installer::passes($checks), 'без vendor і public/build встановлювати не можна');
        $this->assertTrue(Installer::passes([['PHP', true, true, ''], ['sockets', false, false, '']]), 'бажане розширення не блокує');

        $this->assertFalse($installer->isInstalled());
        file_put_contents($installer->lockFile(), 'x');
        $this->assertTrue($installer->isInstalled());

        unlink($installer->lockFile());
        rmdir("{$dir}/storage");
        rmdir($dir);
    }

    public function test_database_error_is_reported(): void
    {
        $error = (new Installer(base_path()))->testDatabase(['host' => '127.0.0.1', 'port' => '1', 'database' => 'x', 'username' => 'x', 'password' => 'x']);

        $this->assertStringStartsWith('Не вдалося підключитися до бази', (string) $error);
    }
}
