<?php

declare(strict_types=1);

namespace FluxBB\User\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use FluxBB\Shared\Infrastructure\Security\PasswordHasher;
use FluxBB\User\Domain\Email;
use FluxBB\User\Domain\GroupId;
use FluxBB\User\Domain\User;
use FluxBB\User\Domain\UserId;
use FluxBB\User\Domain\Username;
use FluxBB\User\Domain\UserRepository;

/**
 * Doctrine DBAL implementation of UserRepository.
 *
 * Maps User domain entities to/from the forum_users table.
 * Handles legacy LEGACY_HASH: prefixed passwords (for migration).
 */
class DoctrineUserRepository implements UserRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PasswordHasher $passwordHasher,
    ) {}

    public function findById(UserId $id): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM forum_users WHERE id = ?',
            [$id->toInt()]
        );
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByUsername(Username $username): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM forum_users WHERE username = ?',
            [$username->toString()]
        );
        return $row === false ? null : $this->hydrate($row);
    }

    public function findByEmail(Email $email): ?User
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM forum_users WHERE email = ?',
            [$email->toString()]
        );
        return $row === false ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM forum_users ORDER BY id'
        );
        return array_map(fn (array $row): User => $this->hydrate($row), $rows);
    }

    public function save(User $user): void
    {
        $data = [
            'username' => $user->getUsername()->toString(),
            'password' => $user->getPasswordHash(),
            'email' => $user->getEmail()->toString(),
            'group_id' => $user->getGroupId()->value,
            'registered' => $user->getRegisteredAt()->getTimestamp(),
            'registration_ip' => $user->getRegistrationIp() ?? '',
        ];

        if ($user->identity()->toInt() === 0) {
            $this->connection->insert('forum_users', $data);
        } else {
            $this->connection->update('forum_users', $data, ['id' => $user->identity()->toInt()]);
        }
    }

    public function delete(User $user): void
    {
        $this->connection->delete('forum_users', ['id' => $user->identity()->toInt()]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): User
    {
        return new User(
            id: new UserId((int) $row['id']),
            username: new Username((string) $row['username']),
            email: new Email((string) $row['email']),
            groupId: GroupId::tryFrom((int) ($row['group_id'] ?? 4)) ?? GroupId::Member,
            passwordHash: (string) ($row['password'] ?? ''),
            registeredAt: new \DateTimeImmutable('@' . ($row['registered'] ?? time())),
            registrationIp: isset($row['registration_ip']) ? (string) $row['registration_ip'] : null,
            lastPostIp: isset($row['last_post_ip']) ? (string) $row['last_post_ip'] : null,
            lastVisit: isset($row['last_visit']) ? new \DateTimeImmutable('@' . $row['last_visit']) : null,
            isAdmmod: in_array((int) ($row['group_id'] ?? 4), [1, 2], true),
        );
    }
}