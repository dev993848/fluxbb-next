<?php

declare(strict_types=1);

namespace FluxBB\Moderation\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use FluxBB\Moderation\Domain\Ban;
use FluxBB\Moderation\Domain\BanRepository;
use FluxBB\Moderation\Domain\IpMask;
use FluxBB\User\Domain\UserId;

/**
 * Doctrine DBAL implementation of the BanRepository interface.
 *
 * Reads ban data from the forum_bans table.
 */
class DoctrineBanRepository implements BanRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function findAllActive(\DateTimeImmutable $now): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_bans WHERE expire IS NULL OR expire > ? ORDER BY id',
            [$now->getTimestamp()]
        );

        return array_map(fn (array $row): Ban => $this->hydrateBan($row), $rows);
    }

    public function findById(int $id): ?Ban
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM forum_bans WHERE id = ?',
            [$id]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrateBan($row);
    }

    public function findByIp(string $ip): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_bans WHERE expire IS NULL OR expire > ? ORDER BY id',
            [time()]
        );

        $bans = array_map(fn (array $row): Ban => $this->hydrateBan($row), $rows);

        return array_values(
            array_filter(
                $bans,
                fn (Ban $ban): bool => $ban->getIpMask() !== null && $ban->getIpMask()->matches($ip)
            )
        );
    }

    public function save(Ban $ban): void
    {
        $this->connection->insert('forum_bans', [
            'ip' => $ban->getIpMask()?->toString(),
            'email' => $ban->getEmail(),
            'username' => $ban->getUsername(),
            'message' => $ban->getMessage(),
            'expire' => $ban->getExpiresAt()?->getTimestamp(),
        ]);
    }

    public function delete(Ban $ban): void
    {
        $this->connection->delete('forum_bans', ['id' => $ban->getId()]);
    }

    /** @param array<string, mixed> $row */
    private function hydrateBan(array $row): Ban
    {
        $ipMask = null;
        if (isset($row['ip']) && $row['ip'] !== '' && $row['ip'] !== null) {
            $ipMask = new IpMask((string) $row['ip']);
        }

        $expiresAt = null;
        if (isset($row['expire']) && $row['expire'] !== null && $row['expire'] !== '') {
            $expiresAt = new \DateTimeImmutable('@' . $row['expire']);
        }

        $createdBy = null;
        if (isset($row['creator_id']) && $row['creator_id'] !== null) {
            $createdBy = new UserId((int) $row['creator_id']);
        }

        $createdAt = new \DateTimeImmutable();
        if (isset($row['created_at']) && $row['created_at'] !== null) {
            $createdAt = new \DateTimeImmutable('@' . $row['created_at']);
        }

        return new Ban(
            id: (int) $row['id'],
            ipMask: $ipMask,
            email: isset($row['email']) ? (string) $row['email'] : null,
            username: isset($row['username']) ? (string) $row['username'] : null,
            message: isset($row['message']) ? (string) $row['message'] : null,
            expiresAt: $expiresAt,
            createdBy: $createdBy,
            createdAt: $createdAt,
        );
    }
}