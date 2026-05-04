<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/helpers/utils.php';
load_env(base_path('.env'));

$db = config('database');
$database = $db['database'];
$serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);

$pdo = new PDO($serverDsn, $db['username'], $db['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    str_replace('`', '``', $database)
));
$pdo->exec(sprintf('USE `%s`', str_replace('`', '``', $database)));

$files = glob(base_path('database/migrations/*.sql')) ?: [];
sort($files);

foreach ($files as $file) {
    $sql = trim((string) file_get_contents($file));
    if ($sql === '') {
        continue;
    }

    $pdo->exec($sql);
    echo 'Migrated: ' . basename($file) . PHP_EOL;
}

if (($argv[1] ?? '') === '--seed') {
    $seed = trim((string) file_get_contents(base_path('database/seeds/seed.sql')));
    if ($seed !== '') {
        $pdo->exec($seed);
        echo 'Seeded: seed.sql' . PHP_EOL;
    }
}
