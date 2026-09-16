<?php

declare(strict_types=1);

namespace FluxBB\Shared\Infrastructure\Security;

use FluxBB\User\Domain\User;
use FluxBB\User\Domain\UserId;

/**
 * Cookie-based authentication provider.
 *
 * Replicates FluxBB 1.5's check_cookie() logic using modern
 * Symfony HttpFoundation components with HMAC-based verification.
 *
 * The cookie stores: {userId}:{passwordHash}
 * with server-side verification against the stored hash.
 */
class CookieAuthProvider
{
    private const string COOKIE_SEED = 'fluxbb_auth_seed';

    /**
     * @param string $cookieName The cookie name (from config)
     * @param string $seed The HMAC seed
     */
    public function __construct(
        private readonly string $cookieName,
        private readonly string $seed,
    ) {}

    /**
     * Generate a login cookie value.
     *
     * @param User $user The authenticated user
     * @return string Cookie value: {id}:{hmac}
     */
    public function generateCookie(User $user): string
    {
        $expires = time() + 1209600; // 14 days
        $userId = $user->getId()->toInt();
        $hmac = $this->computeHmac((string) $userId, $expires);

        return sprintf('%d|%d|%s', $userId, $expires, $hmac);
    }

    /**
     * Validate a cookie and return the user ID.
     *
     * @param string $cookieValue Raw cookie value
     * @return int|null User ID if valid, null otherwise
     */
    public function validateCookie(string $cookieValue): ?int
    {
        $parts = explode('|', $cookieValue);
        if (count($parts) !== 3) {
            return null;
        }

        $userId = (int) $parts[0];
        $expires = (int) $parts[1];
        $hmac = $parts[2];

        if ($expires < time()) {
            return null;
        }

        $expected = $this->computeHmac((string) $userId, $expires);
        if (!hash_equals($expected, $hmac)) {
            return null;
        }

        return $userId;
    }

    /**
     * Compute HMAC for cookie signing.
     */
    private function computeHmac(string $userId, int $expires): string
    {
        return hash_hmac('sha256', $userId . '|' . $expires, $this->seed);
    }
}