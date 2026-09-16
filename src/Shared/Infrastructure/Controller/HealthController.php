<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Healthcheck endpoint для мониторинга и балансировщиков.
 *
 * Проверяет:
 * - Подключение к Базе Данных (SELECT 1)
 * - Наличие Redis (если класс Redis существует)
 *
 * Ответ: HTTP 200 с JSON {"status":"ok"} или 500 с ошибкой.
 */
class HealthController
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function check(): Response
    {
        $checks = [
            'database' => false,
            'redis' => false,
        ];

        $errors = [];

        // Проверка PostgreSQL
        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = true;
        } catch (\Throwable $e) {
            $errors[] = 'database: ' . $e->getMessage();
        }

        // Проверка Redis
        if (class_exists(\Redis::class)) {
            try {
                $host = $_ENV['REDIS_HOST'] ?? 'redis';
                $port = (int) ($_ENV['REDIS_PORT'] ?? 6379);
                $redis = new \Redis();
                $redis->connect($host, $port, 2.0);
                $checks['redis'] = $redis->ping() === true;
                $redis->close();
            } catch (\Throwable $e) {
                // Redis не обязателен — если нет, кеш работает на Filesystem
                $checks['redis'] = null;
            }
        } else {
            $checks['redis'] = null; // расширение не установлено, не проверяем
        }

        $allOk = $checks['database'] === true;

        $status = $allOk ? 'ok' : 'degraded';

        $data = [
            'status' => $status,
            'checks' => $checks,
            'timestamp' => date('c'),
        ];

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return new Response('{"status":"error"}', 500, ['Content-Type' => 'application/json']);
        }

        $httpCode = $allOk ? 200 : 500;

        return new Response($json, $httpCode, ['Content-Type' => 'application/json']);
    }
}