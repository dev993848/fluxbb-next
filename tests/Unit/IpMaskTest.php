<?php

declare(strict_types=1);

use FluxBB\Moderation\Domain\IpMask;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the IpMask value object (domain scar).
 *
 * Covers:
 * - Exact IP match
 * - Wildcard "*.*.*.*" matches any IP
 * - Partial mask "192.168.*.*"
 * - Single octet wildcard "192.168.1.*"
 * - Non-matching IP correctly rejected
 * - Invalid mask formats rejected
 *
 * @see https://github.com/fluxbb/fluxbb/issues/58
 */
class IpMaskTest extends TestCase
{
    public function test_exact_ip_match(): void
    {
        $mask = new IpMask('192.168.1.1');
        $this->assertTrue($mask->matches('192.168.1.1'));
        $this->assertFalse($mask->matches('192.168.1.2'));
    }

    public function test_full_wildcard_matches_all(): void
    {
        $mask = new IpMask('*.*.*.*');
        $this->assertTrue($mask->matches('1.2.3.4'));
        $this->assertTrue($mask->matches('192.168.0.1'));
        $this->assertTrue($mask->matches('10.0.0.1'));
    }

    public function test_partial_mask(): void
    {
        $mask = new IpMask('192.168.*.*');
        $this->assertTrue($mask->matches('192.168.1.1'));
        $this->assertTrue($mask->matches('192.168.0.5'));
        $this->assertFalse($mask->matches('10.0.0.1'));
    }

    public function test_single_octet_wildcard(): void
    {
        $mask = new IpMask('192.168.1.*');
        $this->assertTrue($mask->matches('192.168.1.100'));
        $this->assertFalse($mask->matches('192.168.2.1'));
    }

    public function test_invalid_formats_rejected(): void
    {
        $this->assertFalse(IpMask::isValid('not-an-ip'));
        $this->assertFalse(IpMask::isValid('256.1.1.1'));  // > 255
        $this->assertFalse(IpMask::isValid('192.168.1'));   // 3 octets
        $this->assertFalse(IpMask::isValid(''));            // empty
    }

    public function test_constructor_validates(): void
    {
        $this->expectException(\DomainException::class);
        new IpMask('invalid');
    }

    public function test_equals_comparison(): void
    {
        $mask1 = new IpMask('192.168.*.*');
        $mask2 = new IpMask('192.168.*.*');
        $mask3 = new IpMask('10.0.*.*');

        $this->assertTrue($mask1->equals($mask2));
        $this->assertFalse($mask1->equals($mask3));
    }

    public function test_valid_formats_accepted(): void
    {
        $this->assertTrue(IpMask::isValid('0.0.0.0'));
        $this->assertTrue(IpMask::isValid('255.255.255.255'));
        $this->assertTrue(IpMask::isValid('192.168.0.1'));
        $this->assertTrue(IpMask::isValid('*.*.*.*'));
        $this->assertTrue(IpMask::isValid('192.168.*.*'));
        $this->assertTrue(IpMask::isValid('192.*.1.*'));
    }
}