<?php

declare(strict_types=1);

namespace FluxBB\User\Domain;

/**
 * Repository interface for the User aggregate.
 *
 * Defines the persistence contract for user lookup, creation, and updates.
 * Implementations handle transaction management and unique constraint checking.
 */
interface UserRepository
{
    public function findById(UserId $id): ?User;

    public function findByUsername(Username $username): ?User;

    public function findByEmail(Email $email): ?User;

    /** @return list<User> */
    public function findAll(): array;

    public function save(User $user): void;

    public function delete(User $user): void;
}