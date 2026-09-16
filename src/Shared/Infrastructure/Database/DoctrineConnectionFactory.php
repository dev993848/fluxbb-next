<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Factory for creating Doctrine DBAL connections from container configuration.
 *
 * DBAL 4 uses Middleware for logging instead of deprecated SQLLogger/DebugStack.
 * When a QueryCollector is provided (via container), it wires the logging middleware.
 */
class DoctrineConnectionFactory
{
    /**
     * Create a Doctrine DBAL Connection from container configuration.
     *
     * @param ContainerInterface $container The application DI container
     * @return Connection The configured database connection
     * @throws DBALException if connection cannot be established
     */
    public static function create(ContainerInterface $container): Connection
    {
        /** @var string $driver */
        $driver = $container->get('db.driver');
        /** @var string $host */
        $host = $container->get('db.host');
        /** @var string $port */
        $port = $container->get('db.port');
        /** @var string $name */
        $name = $container->get('db.name');
        /** @var string $user */
        $user = $container->get('db.user');
        /** @var string $password */
        $password = $container->get('db.password');
        /** @var string $charset */
        $charset = $container->get('db.charset');

        $connectionParams = [
            'driver' => $driver,
            'host' => $host,
            'port' => (int) $port,
            'dbname' => $name,
            'user' => $user,
            'password' => $password,
            'charset' => $charset,
            'driverOptions' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ];

        // Allow full DSN URL override (e.g., from environment)
        /** @var string|null $dbUrl */
        $dbUrl = $container->has('db.url') ? $container->get('db.url') : null;
        if ($dbUrl !== null) {
            $connectionParams['url'] = $dbUrl;
        }

        // Wire DBAL 4 logging middleware when QueryCollector is available
        $configuration = new Configuration();

        if ($container->has(QueryCollector::class)) {
            /** @var QueryCollector $collector */
            $collector = $container->get(QueryCollector::class);
            $configuration->setMiddlewares([
                new LoggingMiddleware($collector),
            ]);
        }

        $connectionParams['configuration'] = $configuration;

        return DriverManager::getConnection($connectionParams);
    }
}