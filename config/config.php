<?php

declare(strict_types=1);

/**
 * FluxBB — Application Configuration.
 *
 * This file defines base configuration values read from environment variables
 * or sensible defaults. Values are consumed by config/services.php and by
 * infrastructure services through the PHP-DI container.
 *
 * @see config/services.php  for service definitions
 * @see config/config_dev.php for environment-specific overrides
 *
 * Environment variables (.env file):
 * - APP_ENV:       Application environment (dev, prod, test)
 * - APP_DEBUG:     Enable debug mode (1/0)
 * - DB_DRIVER:     Database driver (pdo_pgsql, pdo_mysql)
 * - DB_HOST:       Database host
 * - DB_PORT:       Database port
 * - DB_NAME:       Database name
 * - DB_USER:       Database user
 * - DB_PASSWORD:   Database password
 * - COOKIE_NAME:   Session cookie name
 * - COOKIE_SEED:   Cookie HMAC seed
 * - DB_PREFIX:     Database table prefix
 * - CACHE_DSN:     Cache backend DSN (redis://localhost:6379, apcu://, file:///var/cache)
 * - MAILER_DSN:    Mailer DSN (smtp://user:pass@mailhost:25, native://default)
 * - MAILER_FROM:   Default sender email
 * - MAILER_FROM_NAME: Default sender name
 */

use function DI\create;
use function DI\get;

return [
    // --- Environment ---
    'env' => $_SERVER['APP_ENV'] ?? 'prod',
    'debug' => (bool) ($_SERVER['APP_DEBUG'] ?? false),

    // --- Database ---
    'db.driver' => $_SERVER['DB_DRIVER'] ?? 'pdo_pgsql',
    'db.host' => $_SERVER['DB_HOST'] ?? '127.0.0.1',
    'db.port' => $_SERVER['DB_PORT'] ?? '5432',
    'db.name' => $_SERVER['DB_NAME'] ?? 'fluxbb',
    'db.user' => $_SERVER['DB_USER'] ?? 'fluxbb',
    'db.password' => $_SERVER['DB_PASSWORD'] ?? 'fluxbb_secret',
    'db.charset' => 'utf8',

    // --- Cache ---
    'cache.dsn' => $_SERVER['CACHE_DSN'] ?? 'file://' . FLUXBB_ROOT . '/var/cache',
    'cache.namespace' => $_SERVER['CACHE_NAMESPACE'] ?? 'fluxbb_',

    // --- Mailer ---
    'mailer.dsn' => $_SERVER['MAILER_DSN'] ?? 'native://default',
    'mailer.from' => $_SERVER['MAILER_FROM'] ?? 'noreply@fluxbb.local',
    'mailer.from_name' => $_SERVER['MAILER_FROM_NAME'] ?? 'FluxBB Forum',

    // --- Forum settings (from original config.php) ---
    'cookie.name' => $_SERVER['COOKIE_NAME'] ?? 'fluxbb_cookie',
    'cookie.seed' => $_SERVER['COOKIE_SEED'] ?? '',
    'db.prefix' => $_SERVER['DB_PREFIX'] ?? 'forum_',
    'forum.cookie_name' => $_SERVER['COOKIE_NAME'] ?? 'fluxbb_cookie',
    'forum.cookie_seed' => $_SERVER['COOKIE_SEED'] ?? '',
    'forum.table_prefix' => $_SERVER['DB_PREFIX'] ?? 'forum_',
    'forum.cache_dir' => FLUXBB_ROOT . '/var/cache',
    'forum.default_lang' => $_SERVER['DEFAULT_LANG'] ?? 'English',
    'forum.default_style' => $_SERVER['DEFAULT_STYLE'] ?? 'Air',

    // --- PSR Service Aliases ---
    Psr\Container\ContainerInterface::class => get(DI\Container::class),
    Psr\EventDispatcher\EventDispatcherInterface::class
        => get(FluxBB\Shared\Infrastructure\Bus\SimpleEventDispatcher::class),
    Psr\SimpleCache\CacheInterface::class
        => get(\Psr\SimpleCache\CacheInterface::class),
    Psr\Log\LoggerInterface::class
        => create(Psr\Log\NullLogger::class),
];