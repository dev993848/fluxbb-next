<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Shared\Infrastructure\Security\CookieAuthProvider;
use FluxBB\User\Domain\Email;
use FluxBB\User\Domain\GroupId;
use FluxBB\User\Domain\User;
use FluxBB\User\Domain\UserId;
use FluxBB\User\Domain\Username;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CookieAuthProvider.
 */
class CookieAuthProviderTest extends TestCase
{
    private CookieAuthProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new CookieAuthProvider(
            cookieName: 'fluxbb_test',
            seed: 'test-seed-12345',
        );
    }

    public function testGenerateAndValidateCookie(): void
    {
        $user = new User(
            id: new UserId(1),
            username: new Username('admin'),
            email: new Email('admin@example.com'),
            groupId: GroupId::Admin,
            passwordHash: 'argon2id_hash',
        );

        $cookie = $this->provider->generateCookie($user);
        $this->assertNotEmpty($cookie);
        $this->assertStringContainsString('1|', $cookie);

        $userId = $this->provider->validateCookie($cookie);
        $this->assertSame(1, $userId);
    }

    public function testInvalidCookieFormatReturnsNull(): void
    {
        $userId = $this->provider->validateCookie('invalid');
        $this->assertNull($userId);
    }

    public function testTamperedCookieReturnsNull(): void
    {
        $user = new User(
            id: new UserId(2),
            username: new Username('user'),
            email: new Email('user@example.com'),
            groupId: GroupId::Member,
            passwordHash: 'hash',
        );

        $cookie = $this->provider->generateCookie($user);
        $tampered = substr_replace($cookie, '3', 0, 1); // Change user ID

        $userId = $this->provider->validateCookie($tampered);
        $this->assertNull($userId);
    }
}