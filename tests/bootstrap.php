<?php

declare(strict_types=1);

/**
 * Test bootstrap for FluxBB Next.
 */

// Define the project root
define('FLUXBB_ROOT', realpath(__DIR__ . '/..'));

// Load Composer autoloader
require FLUXBB_ROOT . '/vendor/autoload.php';

// Load test fixtures
require __DIR__ . '/Fixtures/Events.php';

// Load environment
if (file_exists(FLUXBB_ROOT . '/.env.test')) {
    $dotenv = Dotenv\Dotenv::createImmutable(FLUXBB_ROOT, '.env.test');
    $dotenv->load();
} elseif (file_exists(FLUXBB_ROOT . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(FLUXBB_ROOT);
    $dotenv->load();
}