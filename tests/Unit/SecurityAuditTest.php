<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Security audit tests.
 *
 * Tests key security invariants across the application.
 */
class SecurityAuditTest extends TestCase
{
    /**
     * Ensure all password hashes are Argon2id or bcrypt.
     */
    public function testPasswordHashesAreModern(): void
    {
        $hasher = new \FluxBB\Shared\Infrastructure\Security\PasswordHasher();

        $hash = $hasher->hash('test-password');

        // Must start with Argon2id or bcrypt prefix
        $this->assertTrue(
            str_starts_with($hash, '$argon2id$') || str_starts_with($hash, '$2y$'),
            'Password hash must use Argon2id or bcrypt'
        );
    }

    /**
     * Ensure CsrfMiddleware validates tokens.
     */
    public function testCsrfRejectsMissingToken(): void
    {
        $request = \Symfony\Component\HttpFoundation\Request::create('/post', 'POST');
        $session = new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
        );
        $request->setSession($session);

        $csrf = new \FluxBB\Shared\Infrastructure\Security\CsrfMiddleware();
        $result = $csrf->handle($request);

        $this->assertNotNull($result);
        $this->assertSame(403, $result->getStatusCode());
    }

    /**
     * Ensure RateLimiterMiddleware blocks excessive requests.
     */
    public function testRateLimiterBlocksExcess(): void
    {
        $cache = new \FluxBB\Shared\Infrastructure\Cache\FileCache(
            sys_get_temp_dir() . '/fluxbb_rate_test_' . uniqid('', true)
        );
        $limiter = new \FluxBB\Shared\Infrastructure\Security\RateLimiterMiddleware($cache, 1, 60);

        $request = \Symfony\Component\HttpFoundation\Request::create('/test');

        // First request: allowed
        $this->assertNull($limiter->handle($request));

        // Second request: blocked
        $blocked = $limiter->handle($request);
        $this->assertNotNull($blocked);
        $this->assertSame(429, $blocked->getStatusCode());
    }
}