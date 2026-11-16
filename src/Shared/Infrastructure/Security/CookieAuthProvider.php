<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Security;

use FluxBB\User\Domain\User;

/**
 * Cookie-based authentication provider ("remember me").
 *
 * Cookie format: {userId}|{expires}|{hmac}
 * HMAC covers userId, expiry, and a fingerprint of the password hash so that
 * changing the password invalidates existing remember-me cookies.
 */
class CookieAuthProvider
{
    private const string REMEMBER_COOKIE = 'fluxbb_remember';

    /**
     * @param string $cookieName Session cookie name (config; remember uses fixed name)
     * @param string $seed The HMAC seed
     */
    public function __construct(
        private readonly string $cookieName,
        private readonly string $seed,
    ) {}

    public function getRememberCookieName(): string
    {
        return self::REMEMBER_COOKIE;
    }

    /**
     * Generate a login cookie value.
     *
     * @return string Cookie value: {id}|{expires}|{hmac}
     */
    public function generateCookie(User $user): string
    {
        $expires = time() + 1209600; // 14 days
        $userId = $user->getId()->toInt();
        $hmac = $this->computeHmac((string) $userId, $expires, $user->getPasswordHash());

        return sprintf('%d|%d|%s', $userId, $expires, $hmac);
    }

    /**
     * Parse cookie and return user id if the signature matches the given password hash.
     *
     * @return int|null User ID if valid, null otherwise
     */
    public function validateCookie(string $cookieValue, string $passwordHash): ?int
    {
        $parts = explode('|', $cookieValue);
        if (count($parts) !== 3) {
            return null;
        }

        $userId = (int) $parts[0];
        $expires = (int) $parts[1];
        $hmac = $parts[2];

        if ($userId < 1 || $expires < time()) {
            return null;
        }

        $expected = $this->computeHmac((string) $userId, $expires, $passwordHash);
        if (!hash_equals($expected, $hmac)) {
            return null;
        }

        return $userId;
    }

    /**
     * Extract user id from cookie without verifying password (for DB lookup).
     */
    public function peekUserId(string $cookieValue): ?int
    {
        $parts = explode('|', $cookieValue);
        if (count($parts) !== 3) {
            return null;
        }

        $userId = (int) $parts[0];

        return $userId > 0 ? $userId : null;
    }

    private function computeHmac(string $userId, int $expires, string $passwordHash): string
    {
        $fingerprint = hash('sha256', $passwordHash);

        return hash_hmac('sha256', $userId . '|' . $expires . '|' . $fingerprint, $this->seed);
    }
}
