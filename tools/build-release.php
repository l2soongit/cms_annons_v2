<?php

/**
 * Архів для віртуального хостингу: php tools/build-release.php [--skip-assets]
 *
 * 1. Збирає стилі й скрипти (npm run build), якщо не передано --skip-assets.
 * 2. Копіює в dist/graj-club/ файли з git (без тестів, демо дизайну, CI) і зібрані public/build.
 * 3. Ставить залежності без dev-пакетів (composer install --no-dev) — Filament публікує свої стилі сам.
 * 4. Пакує в dist/graj-club-<версія>.zip. Розпакувати на хостингу й відкрити /install.php — див. INSTALL.md.
 */
$root = dirname(__DIR__);
chdir($root);
$skipAssets = in_array('--skip-assets', $argv, true);

$run = function (string $cmd, ?string $cwd = null): void {
    echo "› {$cmd}\n";
    $proc = proc_open($cmd, [STDIN, STDOUT, STDERR], $pipes, $cwd);
    if (! is_resource($proc) || proc_close($proc) !== 0) {
        fwrite(STDERR, "Команда завершилася з помилкою: {$cmd}\n");
        exit(1);
    }
};

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Потрібне розширення PHP zip\n");
    exit(1);
}

$version = trim((string) shell_exec('git describe --tags --always 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'))) ?: date('Ymd');
$version = date('Y.m.d').'-'.preg_replace('/[^A-Za-z0-9.\-]/', '', $version);

if (! $skipAssets) {
    $run('npm run build');
}
if (! is_file("{$root}/public/build/manifest.json")) {
    fwrite(STDERR, "Немає public/build — запустіть npm run build\n");
    exit(1);
}

// Що не потрібно на хостингу
$exclude = ['tests/', 'design/', '.github/', 'tools/', 'node_modules/', 'phpunit.xml', 'phpstan.neon', '.editorconfig', '.gitattributes', '.env.example', 'package.json', 'package-lock.json', 'vite.config.js', 'resources/css/', 'resources/js/'];
$files = array_filter(explode("\n", trim((string) shell_exec('git ls-files'))), function (string $f) use ($exclude) {
    foreach ($exclude as $prefix) {
        if ($f === $prefix || str_starts_with($f, $prefix)) {
            return false;
        }
    }

    return true;
});

$stage = "{$root}/dist/graj-club";
$rm = function (string $dir) use (&$rm): void {
    if (! is_dir($dir) || is_link($dir)) {
        @unlink($dir);

        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item !== '.' && $item !== '..') {
            $rm("{$dir}/{$item}");
        }
    }
    rmdir($dir);
};
$rm($stage);
@mkdir($stage, 0755, true);

$copy = function (string $rel) use ($root, $stage): void {
    $to = "{$stage}/{$rel}";
    @mkdir(dirname($to), 0755, true);
    copy("{$root}/{$rel}", $to);
};
foreach ($files as $f) {
    $copy($f);
}
// Зібрані Vite-файли (у git їх немає)
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/public/build", FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $copy(str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)));
}
echo 'Скопійовано файлів: '.(count($files) + iterator_count($it))."\n";

// Залежності без dev-пакетів; post-autoload-dump публікує стилі Filament у public/
$run('composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist', $stage);

// Кеш конфігурації з машини збирання не має потрапити на хостинг
foreach (glob("{$stage}/bootstrap/cache/*.php") as $cached) {
    if (! in_array(basename($cached), ['packages.php', 'services.php'], true)) {
        unlink($cached);
    }
}
foreach (['.env', 'storage/installed', 'public/hot', 'public/storage', 'database/database.sqlite'] as $f) {
    @unlink("{$stage}/{$f}");
}

$zipPath = "{$root}/dist/graj-club-{$version}.zip";
@unlink($zipPath);
$zip = new ZipArchive;
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
$count = 0;
foreach ($it as $file) {
    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($stage) + 1));
    if ($file->isDir()) {
        $zip->addEmptyDir($rel);
        $zip->setExternalAttributesName($rel.'/', ZipArchive::OPSYS_UNIX, (0755 | 0040000) << 16);
    } else {
        $zip->addFile($file->getPathname(), $rel);
        $zip->setExternalAttributesName($rel, ZipArchive::OPSYS_UNIX, ($rel === 'artisan' ? 0755 : 0644) << 16);
        $count++;
    }
}
$zip->close();
// Розпакована копія не потрібна: інакше Pint і PHPStan перевірятимуть її як частину проєкту
$rm($stage);

printf("\nГотово: %s (%d файлів, %.1f МБ)\n", $zipPath, $count, filesize($zipPath) / 1048576);
