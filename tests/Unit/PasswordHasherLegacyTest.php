<?php

declare(strict_types=1);

namespace FluxBB\Tests\Unit;

use FluxBB\Shared\Infrastructure\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PasswordHasher with LegacyHash support.
 */
class PasswordHasherLegacyTest extends TestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PasswordHasher();
    }

    public function testLegacyHashPrefixDetected(): void
    {
        $hash = $this->hasher->hash('password123');
        // Argon2id or bcrypt — both are valid
        $this->assertTrue(
            str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2id$'),
            'Hash should start with $2y$ or $argon2id$'
        );
    }

    public function testPasswordVerifyWithGoodPassword(): void
    {
        $hash = $this->hasher->hash('correct-horse-battery-staple');
        $this->assertTrue($this->hasher->verify('correct-horse-battery-staple', $hash));
    }

    public function testPasswordVerifyWithWrongPassword(): void
    {
        $hash = $this->hasher->hash('real-password');
        $this->assertFalse($this->hasher->verify('wrong-password', $hash));
    }
}