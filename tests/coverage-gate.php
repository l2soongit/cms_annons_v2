<?php

/*
 * Поріг покриття для критичних модулів (ТЗ 4): голосування і білінг.
 * Запуск у CI після `php artisan test --coverage-clover=clover.xml`:
 *   php tests/coverage-gate.php clover.xml app/Voting=80 app/Billing=80
 */

$clover = $argv[1] ?? 'clover.xml';
$gates = array_slice($argv, 2);

$xml = @simplexml_load_file($clover);
if ($xml === false) {
    fwrite(STDERR, "Cannot read {$clover}\n");
    exit(1);
}

$root = str_replace('\\', '/', dirname(__DIR__)).'/';
$failed = false;

foreach ($gates as $gate) {
    [$dir, $min] = explode('=', $gate) + [1 => 80];
    $statements = $covered = 0;

    foreach ($xml->xpath('//file') as $file) {
        $path = str_replace('\\', '/', (string) $file['name']);
        if (str_starts_with(str_replace($root, '', $path), rtrim($dir, '/').'/')) {
            $statements += (int) $file->metrics['statements'];
            $covered += (int) $file->metrics['coveredstatements'];
        }
    }

    $percent = $statements ? round($covered / $statements * 100, 1) : 0.0;
    $ok = $statements > 0 && $percent >= (float) $min;
    $failed = $failed || ! $ok;
    printf("%s %-12s %5.1f%% (%d/%d, min %s%%)\n", $ok ? 'OK  ' : 'FAIL', $dir, $percent, $covered, $statements, $min);
}

exit($failed ? 1 : 0);
