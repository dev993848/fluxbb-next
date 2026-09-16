<?php

declare(strict_types=1);

use FluxBB\Shared\Infrastructure\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

class PasswordHasherTest extends TestCase
{
    private PasswordHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PasswordHasher();
    }

    public function test_hash_creates_valid_hash(): void
    {
        $hash = $this->hasher->hash('test_password');
        $this->assertNotEmpty($hash);
        $this->assertStringStartsWith('$', $hash);
    }

    public function test_verify_correct_password(): void
    {
        $hash = $this->hasher->hash('test_password');
        $this->assertTrue($this->hasher->verify('test_password', $hash));
    }

    public function test_verify_wrong_password(): void
    {
        $hash = $this->hasher->hash('test_password');
        $this->assertFalse($this->hasher->verify('wrong_password', $hash));
    }

    public function test_needs_rehash_old_bcrypt_hash(): void
    {
        $oldHash = password_hash('test', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->assertTrue($this->hasher->needsRehash($oldHash));
    }
}