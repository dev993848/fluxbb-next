<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use FluxBB\Shared\Domain\ConfigRepository;

/**
 * Doctrine DBAL implementation of ConfigRepository.
 *
 * Manages the forum_config key-value store table.
 */
class DoctrineConfigRepository implements ConfigRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function find(string $key): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT conf_value FROM forum_config WHERE conf_name = ?',
            [$key]
        );
        return $result === false ? null : (string) $result;
    }

    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT conf_name, conf_value FROM forum_config ORDER BY conf_name'
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['conf_name']] = (string) $row['conf_value'];
        }
        return $result;
    }

    public function upsert(string $key, string $value): void
    {
        $this->connection->executeStatement(
            'INSERT INTO forum_config (conf_name, conf_value) VALUES (?, ?)
             ON CONFLICT (conf_name) DO UPDATE SET conf_value = EXCLUDED.conf_value',
            [$key, $value]
        );
    }

    public function delete(string $key): void
    {
        $this->connection->delete('forum_config', ['conf_name' => $key]);
    }
}