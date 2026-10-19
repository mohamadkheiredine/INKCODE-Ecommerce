<?php

use Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Test database per worktree slot
|--------------------------------------------------------------------------
|
| Parallel git worktrees ("slots", see CLAUDE.md) each test against their own
| database, so one suite never wipes another's. A slot names it with
| DB_TEST_DATABASE in its .env (scripts/use-slot.mjs writes it). Without one,
| phpunit.xml's inkcode_testing applies, as in the main checkout and CI.
|
*/

$envFile = dirname(__DIR__).'/.env';

$testDatabase = getenv('DB_TEST_DATABASE') ?: (is_file($envFile)
    ? (Dotenv::parse((string) file_get_contents($envFile))['DB_TEST_DATABASE'] ?? null)
    : null);

if (is_string($testDatabase) && $testDatabase !== '') {
    putenv("DB_DATABASE={$testDatabase}");
    $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $testDatabase;
}
