<?php

/**
 * docker-compose.yml exports DB_DATABASE=virtua_fc into the container as a real
 * environment variable, and PHPUnit's <env> element will not reliably override
 * one of those. RefreshDatabase runs migrate:fresh, so a suite that resolves the
 * development database silently destroys every local save.
 *
 * Forced here, before the autoloader runs and therefore before Laravel reads any
 * configuration. Laravel's parallel testing appends its token to this value
 * (virtua_fc_test_1, _2, ...), which is where the existing paratest databases
 * come from.
 *
 * TestDatabaseIsolationTest asserts the result.
 */
$database = 'virtua_fc_test';

putenv("DB_DATABASE={$database}");
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;

require __DIR__ . '/../vendor/autoload.php';
