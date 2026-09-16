<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Security;

/**
 * Password hashing and verification service.
 *
 * Uses PHP's native password hashing functions with Argon2id as the
 * preferred algorithm. Falls back to bcrypt if Argon2id is not available.
 *
 * Argon2id provides resistance against side-channel and GPU-based attacks.
 * Memory cost, time cost, and thread count are configured for a good
 * security/performance balance.
 */
class PasswordHasher
{
    /** Argon2id memory cost in KiB (64 MB). */
    private const int ARGON_MEMORY_COST = 65536;

    /** Argon2id time cost. */
    private const int ARGON_TIME_COST = 4;

    /** Argon2id thread count. */
    private const int ARGON_THREADS = 2;

    /** Bcrypt cost factor (fallback). */
    private const int BCRYPT_COST = 12;

    /**
     * Hash a plain-text password.
     *
     * Uses Argon2id with configured memory/time costs. Falls back to bcrypt
     * if Argon2id is not supported (PHP 7.2 or earlier).
     *
     * @param string $password The plain-text password to hash
     * @return non-empty-string The password hash (includes algorithm, cost, salt)
     */
    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => self::ARGON_MEMORY_COST,
            'time_cost'   => self::ARGON_TIME_COST,
            'threads'     => self::ARGON_THREADS,
        ]);

        if ($hash === false) {
            // Argon2id not available — fallback to bcrypt
            $hash = password_hash($password, PASSWORD_BCRYPT, [
                'cost' => self::BCRYPT_COST,
            ]);
        }

        if ($hash === false) {
            throw new \RuntimeException('Password hashing failed unexpectedly');
        }

        /** @var non-empty-string $hash */
        return $hash;
    }

    /**
     * Verify a password against a hash.
     *
     * @param string $password The plain-text password to check
     * @param string $hash     The stored password hash
     * @return bool True if the password matches the hash
     */
    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if the hash was created with outdated parameters.
     *
     * If the algorithm or cost factors have changed since the hash was
     * created, this returns true and the password should be rehashed
     * on the next successful login.
     *
     * @param string $hash The stored password hash
     * @return bool True if the hash needs to be recreated
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash(
            $hash,
            PASSWORD_ARGON2ID,
            [
                'memory_cost' => self::ARGON_MEMORY_COST,
                'time_cost'   => self::ARGON_TIME_COST,
                'threads'     => self::ARGON_THREADS,
            ]
        );
    }
}