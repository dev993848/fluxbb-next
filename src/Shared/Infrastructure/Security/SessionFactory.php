<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Doctrine\DBAL\Connection;

/**
 * Session factory for FluxBB.
 *
 * Creates Symfony HttpFoundation Session instances backed by
 * database storage (PostgreSQL) for production, or native file storage
 * for development.
 */
class SessionFactory
{
    /**
     * Create a session instance.
     *
     * @param Connection|null $connection Doctrine DBAL connection (null = native file storage)
     * @param string $cookieName Session cookie name
     * @param int $lifetime Session lifetime in seconds (0 = browser session)
     * @return Session
     */
    public static function create(
        ?Connection $connection = null,
        string $cookieName = 'fluxbb_session',
        int $lifetime = 0,
    ): Session {
        if ($connection !== null) {
            /** @psalm-suppress InvalidArgument — DBAL getNativeConnection() returns mixed, but when configured correctly it returns PDO */
            /** @var \PDO $pdo */
            $pdo = $connection->getNativeConnection();
            $storage = new NativeSessionStorage([
                'name' => $cookieName,
                'cookie_lifetime' => $lifetime,
                'cookie_secure' => true,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'gc_maxlifetime' => 86400,
            ], new PdoSessionHandler($pdo, [
                'db_table' => 'forum_sessions',
                'db_id_col' => 'session_id',
                'db_data_col' => 'session_data',
                'db_lifetime_col' => 'session_lifetime',
                'db_time_col' => 'session_time',
            ]));
        } else {
            $storage = new NativeSessionStorage([
                'name' => $cookieName,
                'cookie_lifetime' => $lifetime,
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ]);
        }

        $session = new Session($storage);
        $session->start();

        return $session;
    }
}