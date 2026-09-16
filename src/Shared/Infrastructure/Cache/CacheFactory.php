<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Cache;

use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Factory for creating PSR-16 cache adapters.
 *
 * Supports three backends:
 * - redis: Redis (requires ext-redis or predis/predis)
 * - apcu: APCu (requires ext-apcu)
 * - file: Filesystem (default fallback)
 *
 * Usage in config/services.php:
 *   CacheInterface::class => factory([CacheFactory::class, 'create']),
 */
class CacheFactory
{
    /**
     * Create a PSR-16 cache from DI config.
     *
     * @param ContainerInterface $c PHP-DI container
     * @return CacheInterface
     *
     * @psalm-suppress UndefinedClass, MixedAssignment, MixedArgument, MixedMethodCall
     */
    public static function create(ContainerInterface $c): CacheInterface
    {
        $dsn = (string) ($_ENV['CACHE_DSN'] ?? $c->get('cache.dsn'));
        $namespace = (string) ($_ENV['CACHE_NAMESPANCE'] ?? 'fluxbb_');

        // Redis: CACHE_DSN=redis://localhost:6379/0
        if (str_starts_with($dsn, 'redis://') && class_exists(\Redis::class)) {
            try {
                $redis = RedisAdapter::createConnection($dsn);
                $adapter = new RedisAdapter($redis, $namespace);
                return new Psr16Cache($adapter);
            } catch (\Throwable) {
                // Fall through to filesystem
            }
        }

        // APCu: CACHE_DSN=apcu://
        if (str_starts_with($dsn, 'apcu://') && function_exists('apcu_fetch')) {
            $adapter = new ApcuAdapter($namespace);
            return new Psr16Cache($adapter);
        }

        // Fallback: filesystem
        $cacheDir = FLUXBB_ROOT . '/var/cache';
        $adapter = new FilesystemAdapter($namespace, 0, $cacheDir);
        return new Psr16Cache($adapter);
    }
}